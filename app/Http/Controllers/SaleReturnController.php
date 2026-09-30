<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SaleReturnController extends Controller implements HasMiddleware
{
    public function __construct(protected SaleService $service) {}

    public static function middleware(): array
    {
        return static::crudPermissions('sale_returns', ['view' => ['print']]);
    }

    public function index(Request $request)
    {
        // Select2 lookup of invoices that still have returnable quantities.
        if ($request->boolean('lookup')) {
            return $this->lookup($request);
        }

        if ($request->ajax()) {
            $query = SaleReturn::query()->select('sale_returns.*')
                ->with(['sale', 'customer'])
                ->withSum('items as items_qty', 'quantity')
                ->when($request->filled('start_date') && $request->filled('end_date'),
                    fn ($q) => $q->whereBetween('sale_returns.date', [$request->start_date, $request->end_date]))
                ->when($request->customer_id, fn ($q, $v) => $q->where('sale_returns.customer_id', $v));

            $base = clone $query;
            $totals = [
                'total' => (float) (clone $base)->sum('sale_returns.total'),
                'refund' => (float) (clone $base)->sum('sale_returns.refund_amount'),
            ];

            return DataTables::eloquent($query)
                ->editColumn('date', fn ($r) => format_date($r->date))
                ->editColumn('return_no', fn ($r) => '<a href="'.route('sale-returns.show', $r).'" class="font-weight-600">'.e($r->return_no).'</a>')
                ->addColumn('invoice_no', fn ($r) => $r->sale ? '<a href="'.route('sales.show', $r->sale_id).'">'.e($r->sale->invoice_no).'</a>' : '-')
                ->addColumn('customer_name', fn ($r) => e($r->customer->display_name ?? '-'))
                ->editColumn('items_qty', fn ($r) => qty_format($r->items_qty))
                ->editColumn('total', fn ($r) => money($r->total))
                ->editColumn('refund_amount', fn ($r) => $r->refund_amount > 0 ? money($r->refund_amount).' <small class="text-muted">'.e(payment_method_label($r->refund_method)).'</small>' : '-')
                ->editColumn('reason', fn ($r) => e($r->reason ?? '-'))
                ->addColumn('action', fn ($r) => $this->actions([
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('sale-returns.show', $r)],
                    ['label' => 'Print Credit Note', 'icon' => 'fas fa-print', 'url' => route('sale-returns.print', $r), 'blank' => true],
                    ['label' => 'Download PDF', 'icon' => 'far fa-file-pdf', 'url' => route('sale-returns.print', [$r, 'download' => 1])],
                    '-',
                    ['label' => 'Delete', 'delete' => route('sale-returns.destroy', $r), 'can' => 'sale_returns.delete', 'message' => 'The credit note will be deleted and returned stock removed again.'],
                ]))
                ->filterColumn('invoice_no', fn ($q, $k) => $q->whereHas('sale', fn ($s) => $s->where('invoice_no', 'like', "%{$k}%")))
                ->filterColumn('customer_name', fn ($q, $k) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$k}%")))
                ->with('totals', $totals)
                ->rawColumns(['return_no', 'invoice_no', 'refund_amount', 'action', 'customer_name', 'reason'])
                ->make(true);
        }

        return view('sale-returns.index');
    }

    protected function lookup(Request $request)
    {
        $term = trim((string) $request->q);
        $page = max(1, (int) $request->page);
        $rows = Sale::with('customer')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('sale_items')
                ->whereColumn('sale_items.sale_id', 'sales.id')->whereColumn('sale_items.returned_quantity', '<', 'sale_items.quantity'))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('invoice_no', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('company', 'like', "%{$term}%"))))
            ->orderByDesc('date')->orderByDesc('id')
            ->skip(($page - 1) * 20)->take(21)->get();

        return response()->json([
            'results' => $rows->take(20)->map(fn ($s) => [
                'id' => $s->id,
                'text' => $s->invoice_no.' - '.($s->customer->name ?? '').' ('.money($s->total).', '.format_date($s->date).')',
            ])->values(),
            'more' => $rows->count() > 20,
        ]);
    }

    public function create(Request $request)
    {
        $sale = $request->sale_id ? Sale::with(['customer', 'items.product.unit'])->find($request->sale_id) : null;

        if (! $sale) {
            return view('sale-returns.select-invoice');
        }

        $factor = $sale->returnDiscountFactor();

        return view('sale-returns.create', compact('sale', 'factor'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', Rule::in(array_keys(payment_methods()))],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = Sale::findOrFail($data['sale_id']);

        try {
            $return = $this->service->createReturn($sale, $data);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('sale-returns.show', $return)->with('success', "Credit note {$return->return_no} created successfully");
    }

    public function show(SaleReturn $saleReturn)
    {
        $saleReturn->load(['sale', 'customer', 'items.product.unit', 'creator']);

        return view('sale-returns.show', ['return' => $saleReturn]);
    }

    public function destroy(SaleReturn $saleReturn)
    {
        try {
            $this->service->deleteReturn($saleReturn);
        } catch (ValidationException $e) {
            return $this->failure(collect($e->errors())->flatten()->first());
        }

        return $this->success("Credit note {$saleReturn->return_no} deleted", route('sale-returns.index'));
    }

    public function print(Request $request, SaleReturn $saleReturn)
    {
        $saleReturn->load(['sale', 'customer', 'items.product.unit', 'creator']);
        $pdf = Pdf::loadView('pdf.credit-note', ['return' => $saleReturn])->setPaper('a4');
        $file = 'CreditNote-'.$saleReturn->return_no.'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }
}
