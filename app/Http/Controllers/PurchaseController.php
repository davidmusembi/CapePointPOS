<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PurchaseController extends Controller implements HasMiddleware
{
    public function __construct(protected PurchaseService $service) {}

    public static function middleware(): array
    {
        return static::crudPermissions('purchases', ['view' => ['payments']]);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $base = Purchase::query()
                ->when($request->start_date && $request->end_date, fn ($q) => $q->whereBetween('purchases.date', [$request->start_date, $request->end_date]))
                ->when($request->supplier_id, fn ($q, $v) => $q->where('purchases.supplier_id', $v))
                ->when($request->payment_status, fn ($q, $v) => $q->where('purchases.payment_status', $v))
                ->when($request->purchase_order_id, fn ($q, $v) => $q->where('purchases.purchase_order_id', $v));

            $totals = (clone $base)->selectRaw('COALESCE(SUM(total),0) total, COALESCE(SUM(paid_amount),0) paid, COALESCE(SUM(returned_amount),0) returned, COALESCE(SUM(due_amount),0) due')
                ->first()->toArray();

            $query = (clone $base)->with(['supplier', 'purchaseOrder', 'creator'])->withCount('returns')->select('purchases.*');

            return DataTables::eloquent($query)
                ->editColumn('date', fn ($p) => format_date($p->date))
                ->editColumn('purchase_no', fn ($p) => '<a href="'.route('purchases.show', $p).'" class="font-weight-600">'.e($p->purchase_no).'</a>')
                ->editColumn('supplier_invoice_no', fn ($p) => e($p->supplier_invoice_no ?: '-'))
                ->addColumn('lpo_no', fn ($p) => $p->purchaseOrder ? '<a href="'.route('purchase-orders.show', $p->purchase_order_id).'">'.e($p->purchaseOrder->lpo_no).'</a>' : '-')
                ->addColumn('supplier_name', fn ($p) => e($p->supplier->display_name ?? '-'))
                ->editColumn('total', fn ($p) => money($p->total))
                ->editColumn('paid_amount', fn ($p) => money($p->paid_amount))
                ->editColumn('returned_amount', fn ($p) => $p->returned_amount > 0 ? money($p->returned_amount) : '-')
                ->editColumn('due_amount', fn ($p) => $p->due_amount > 0 ? '<span class="amount-positive">'.money($p->due_amount).'</span>' : money(0))
                ->editColumn('payment_status', fn ($p) => payment_status_badge($p->payment_status))
                ->addColumn('added_by', fn ($p) => e($p->creator->name ?? '-'))
                ->addColumn('action', fn ($p) => $this->actions($this->rowActions($p)))
                ->filterColumn('supplier_name', fn ($q, $k) => $q->whereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$k}%")->orWhere('company', 'like', "%{$k}%")))
                ->filterColumn('lpo_no', fn ($q, $k) => $q->whereHas('purchaseOrder', fn ($s) => $s->where('lpo_no', 'like', "%{$k}%")))
                ->rawColumns(['purchase_no', 'lpo_no', 'due_amount', 'payment_status', 'action'])
                ->with('totals', $totals)
                ->make(true);
        }

        return view('purchases.index');
    }

    protected function rowActions(Purchase $p): array
    {
        $hasReturns = ($p->returns_count ?? $p->returns()->count()) > 0;

        return array_filter([
            ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('purchases.show', $p)],
            ! $hasReturns ? ['label' => 'Edit', 'icon' => 'fas fa-edit', 'url' => route('purchases.edit', $p), 'can' => 'purchases.edit'] : null,
            '-',
            $p->due_amount > 0 ? ['label' => 'Add Payment', 'icon' => 'fas fa-money-bill-wave', 'modal' => route('payments.create', ['type' => 'supplier', 'purchase_id' => $p->id]), 'can' => 'payments.create'] : null,
            ['label' => 'View Payments', 'icon' => 'fas fa-list', 'modal' => route('purchases.payments', $p), 'can' => 'payments.view'],
            ['label' => 'Purchase Return', 'icon' => 'fas fa-reply-all', 'url' => route('purchase-returns.create', ['purchase_id' => $p->id]), 'can' => 'purchase_returns.create'],
            '-',
            ! $hasReturns ? ['label' => 'Delete', 'delete' => route('purchases.destroy', $p), 'can' => 'purchases.delete', 'message' => 'Stock received on this purchase will be reversed.'] : null,
        ]);
    }

    public function create(Request $request)
    {
        $purchase = new Purchase(['date' => now(), 'discount_type' => 'fixed', 'discount_value' => 0]);
        $items = [];
        $order = null;

        if ($request->purchase_order_id) {
            $order = PurchaseOrder::with(['supplier', 'items.product.unit'])->findOrFail($request->purchase_order_id);
            if (! $order->can_receive) {
                return redirect()->route('purchase-orders.show', $order)->with('error', 'This LPO is '.$order->status.' and cannot receive goods.');
            }
            $purchase->supplier_id = $order->supplier_id;
            $purchase->purchase_order_id = $order->id;
            $purchase->discount_type = $order->discount_type;
            $purchase->discount_value = $order->discount_type === 'percentage' ? $order->discount_value : 0;
            foreach ($order->items as $it) {
                if ($it->pending_quantity > 0 && $it->product) {
                    $items[] = $this->productItem($it->product, $it->pending_quantity, $it->unit_cost, $it->tax_rate, $it->discount_percent)
                        + ['purchase_order_item_id' => $it->id, 'pending' => $it->pending_quantity];
                }
            }
        } elseif ($request->product_id && ($p = Product::with('unit')->find($request->product_id))) {
            $items[] = $this->productItem($p, max(1, $p->alert_quantity - $p->stock_quantity), round($p->cost_price, 2), $p->tax_percent, 0);
        }

        return view('purchases.form', $this->formData($purchase, $items, $order));
    }

    public function store(Request $request)
    {
        try {
            $purchase = $this->service->create($this->validated($request, true));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', "Purchase {$purchase->purchase_no} saved and stock updated");
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'purchaseOrder', 'items.product.unit', 'returns.creator', 'allocations.payment', 'creator']);

        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        if ($purchase->returns()->exists()) {
            return redirect()->route('purchases.show', $purchase)->with('error', 'This purchase has returns and can no longer be edited.');
        }
        $purchase->load(['supplier', 'purchaseOrder', 'items.product.unit', 'items']);
        $items = $purchase->items->map(fn ($i) => $this->productItem($i->product, $i->quantity, $i->unit_cost, $i->tax_rate, $i->discount_percent)
            + ($i->purchase_order_item_id ? ['purchase_order_item_id' => $i->purchase_order_item_id] : []))->all();

        return view('purchases.form', $this->formData($purchase, $items, $purchase->purchaseOrder));
    }

    public function update(Request $request, Purchase $purchase)
    {
        try {
            $data = $this->validated($request, false);
            $data['purchase_order_id'] = $purchase->purchase_order_id;
            $this->service->update($purchase, $data);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', "Purchase {$purchase->purchase_no} updated");
    }

    public function destroy(Purchase $purchase)
    {
        try {
            $this->service->delete($purchase);
        } catch (ValidationException $e) {
            return $this->failure(collect($e->errors())->flatten()->first());
        }

        return $this->success("Purchase {$purchase->purchase_no} deleted and stock reversed", route('purchases.index'));
    }

    public function payments(Purchase $purchase)
    {
        $purchase->load(['allocations.payment.creator', 'supplier']);

        return view('purchases.payments', compact('purchase'));
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

    protected function formData(Purchase $purchase, array $items, ?PurchaseOrder $order): array
    {
        $oldItems = old('items');
        if ($oldItems) {
            $products = Product::with('unit')->whereIn('id', collect($oldItems)->pluck('product_id'))->get()->keyBy('id');
            $items = collect($oldItems)->filter(fn ($r) => isset($products[$r['product_id'] ?? 0]))
                ->map(fn ($r) => $this->productItem($products[$r['product_id']], $r['quantity'] ?? 1, $r['unit_cost'] ?? 0, $r['tax_rate'] ?? 0, $r['discount_percent'] ?? 0)
                    + (! empty($r['purchase_order_item_id']) ? ['purchase_order_item_id' => $r['purchase_order_item_id']] : []))
                ->values()->all();
        }
        $supplierId = old('supplier_id', $purchase->supplier_id);

        return [
            'purchase' => $purchase,
            'order' => $order,
            'items' => $items,
            'supplier' => $supplierId ? Supplier::find($supplierId) : null,
            'taxRates' => TaxRate::orderBy('name')->get(['name', 'rate']),
        ];
    }

    protected function validated(Request $request, bool $creating): array
    {
        $rules = [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:60'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'discount_type' => ['required', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
        if ($creating) {
            $rules += \App\Services\PaymentService::rowRules();
        }

        return $request->validate($rules, ['items.required' => 'Add at least one product.']);
    }
}
