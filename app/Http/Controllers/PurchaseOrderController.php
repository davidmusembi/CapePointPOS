<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\PurchaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public function __construct(protected PurchaseService $service) {}

    public static function middleware(): array
    {
        return static::crudPermissions('purchase_orders', ['view' => ['print'], 'edit' => ['cancel']]);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $base = PurchaseOrder::query()
                ->when($request->start_date && $request->end_date, fn ($q) => $q->whereBetween('purchase_orders.date', [$request->start_date, $request->end_date]))
                ->when($request->supplier_id, fn ($q, $v) => $q->where('purchase_orders.supplier_id', $v))
                ->when($request->status, fn ($q, $v) => $q->where('purchase_orders.status', $v));

            $totals = ['total' => (float) (clone $base)->sum('total')];

            $query = (clone $base)->select('purchase_orders.*')->with(['supplier', 'creator'])->withCount(['items', 'purchases']);

            return DataTables::eloquent($query)
                ->editColumn('date', fn ($o) => format_date($o->date))
                ->editColumn('lpo_no', fn ($o) => '<a href="'.route('purchase-orders.show', $o).'" class="font-weight-600">'.e($o->lpo_no).'</a>')
                ->addColumn('supplier_name', fn ($o) => e($o->supplier->display_name ?? '-'))
                ->editColumn('expected_date', fn ($o) => format_date($o->expected_date) ?: '-')
                ->editColumn('total', fn ($o) => money($o->total))
                ->editColumn('status', fn ($o) => status_badge($o->status))
                ->addColumn('added_by', fn ($o) => e($o->creator->name ?? '-'))
                ->addColumn('action', fn ($o) => $this->actions($this->rowActions($o)))
                ->filterColumn('supplier_name', fn ($q, $k) => $q->whereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$k}%")->orWhere('company', 'like', "%{$k}%")))
                ->rawColumns(['lpo_no', 'status', 'action'])
                ->with('totals', $totals)
                ->make(true);
        }

        return view('purchase-orders.index');
    }

    protected function rowActions(PurchaseOrder $o): array
    {
        return array_filter([
            ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('purchase-orders.show', $o)],
            ['label' => 'Print LPO', 'icon' => 'fas fa-print', 'url' => route('purchase-orders.print', $o), 'blank' => true],
            ['label' => 'Download PDF', 'icon' => 'far fa-file-pdf', 'url' => route('purchase-orders.print', [$o, 'download' => 1])],
            $o->can_receive ? ['label' => 'Receive Goods', 'icon' => 'fas fa-truck-loading', 'url' => route('purchases.create', ['purchase_order_id' => $o->id]), 'can' => 'purchases.create'] : null,
            $o->status === 'pending' ? ['label' => 'Edit', 'icon' => 'fas fa-edit', 'url' => route('purchase-orders.edit', $o), 'can' => 'purchase_orders.edit'] : null,
            '-',
            $o->can_receive ? ['label' => 'Cancel LPO', 'icon' => 'fas fa-ban', 'confirm' => route('purchase-orders.cancel', $o), 'method' => 'PATCH', 'message' => 'Cancel LPO '.$o->lpo_no.'?', 'can' => 'purchase_orders.edit'] : null,
            ! ($o->purchases_count ?? $o->purchases()->count()) ? ['label' => 'Delete', 'delete' => route('purchase-orders.destroy', $o), 'can' => 'purchase_orders.delete'] : null,
        ]);
    }

    public function create(Request $request)
    {
        $order = new PurchaseOrder(['date' => now(), 'discount_type' => 'fixed', 'discount_value' => 0]);
        $items = [];
        if ($request->product_id && ($p = Product::with(['unit', 'taxRate'])->find($request->product_id))) {
            $items[] = $this->productItem($p, max(1, $p->alert_quantity - $p->stock_quantity), round($p->cost_price, 2), $p->tax_percent, 0);
        }

        return view('purchase-orders.form', $this->formData($order, $items));
    }

    public function store(Request $request)
    {
        try {
            $order = $this->service->saveOrder(null, $this->validated($request));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchase-orders.show', $order)->with('success', "LPO {$order->lpo_no} created successfully");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product.unit', 'purchases.creator', 'creator']);

        return view('purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'pending') {
            return redirect()->route('purchase-orders.show', $purchaseOrder)->with('error', 'Only pending LPOs can be edited.');
        }
        $purchaseOrder->load(['supplier', 'items.product.unit']);
        $items = $purchaseOrder->items->map(fn ($i) => $this->productItem($i->product, $i->quantity, $i->unit_cost, $i->tax_rate, $i->discount_percent))->all();

        return view('purchase-orders.form', $this->formData($purchaseOrder, $items));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        try {
            $this->service->saveOrder($purchaseOrder, $this->validated($request));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', "LPO {$purchaseOrder->lpo_no} updated successfully");
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->purchases()->exists()) {
            return $this->failure('Goods have been received against this LPO. Cancel it instead of deleting.');
        }
        $purchaseOrder->delete();

        return $this->success("LPO {$purchaseOrder->lpo_no} deleted", route('purchase-orders.index'));
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if (! $purchaseOrder->can_receive) {
            return $this->failure('Only pending or partially received LPOs can be cancelled.');
        }
        $purchaseOrder->update(['status' => 'cancelled']);

        return $this->success("LPO {$purchaseOrder->lpo_no} cancelled");
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product.unit', 'creator']);
        $pdf = Pdf::loadView('pdf.lpo', ['order' => $purchaseOrder])->setPaper('a4');
        $file = $purchaseOrder->lpo_no.'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    protected function productItem(Product $p, $qty, $cost, $tax, $disc): array
    {
        return [
            'id' => $p->id, 'text' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit->short_name ?? '',
            'cost_price' => (float) $cost, 'price' => (float) $cost, 'quantity' => (float) $qty,
            'tax_rate_line' => (float) $tax, 'discount_percent' => (float) $disc,
            'stock' => (float) $p->stock_quantity, 'track_stock' => $p->track_stock,
        ];
    }

    protected function formData(PurchaseOrder $order, array $items): array
    {
        $oldItems = old('items');
        if ($oldItems) {
            $products = Product::with('unit')->whereIn('id', collect($oldItems)->pluck('product_id'))->get()->keyBy('id');
            $items = collect($oldItems)->filter(fn ($r) => isset($products[$r['product_id'] ?? 0]))
                ->map(fn ($r) => $this->productItem($products[$r['product_id']], $r['quantity'] ?? 1, $r['unit_cost'] ?? 0, $r['tax_rate'] ?? 0, $r['discount_percent'] ?? 0))
                ->values()->all();
        }
        $supplierId = old('supplier_id', $order->supplier_id);

        return [
            'order' => $order,
            'items' => $items,
            'supplier' => $supplierId ? Supplier::find($supplierId) : null,
            'taxRates' => TaxRate::orderBy('name')->get(['name', 'rate']),
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:date'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], ['items.required' => 'Add at least one product.']);
    }
}
