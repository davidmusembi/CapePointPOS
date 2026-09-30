<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Services\DocumentTotals;
use App\Services\PurchaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PurchaseReturnController extends Controller implements HasMiddleware
{
    public function __construct(protected PurchaseService $service) {}

    public static function middleware(): array
    {
        return static::crudPermissions('purchase_returns', ['view' => ['print']]);
    }

    public function index(Request $request)
    {
        // Purchase picker lookup used by the create screen (select2)
        if ($request->boolean('lookup')) {
            $term = trim((string) $request->q);
            $rows = Purchase::with('supplier')
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('purchase_no', 'like', "%{$term}%")
                    ->orWhere('supplier_invoice_no', 'like', "%{$term}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%")->orWhere('company', 'like', "%{$term}%"))))
                ->latest('date')->latest('id')->limit(20)->get();

            return response()->json([
                'results' => $rows->map(fn ($p) => [
                    'id' => $p->id,
                    'text' => $p->purchase_no.' - '.($p->supplier->display_name ?? '').' ('.format_date($p->date).', '.money($p->total).')',
                ])->values(),
                'more' => false,
            ]);
        }

        if ($request->ajax()) {
            $base = PurchaseReturn::query()
                ->when($request->start_date && $request->end_date, fn ($q) => $q->whereBetween('purchase_returns.date', [$request->start_date, $request->end_date]))
                ->when($request->supplier_id, fn ($q, $v) => $q->where('purchase_returns.supplier_id', $v));

            $totals = (clone $base)->selectRaw('COALESCE(SUM(total),0) total, COALESCE(SUM(refund_amount),0) refund')->first()->toArray();
            $query = (clone $base)->with(['purchase', 'supplier'])->select('purchase_returns.*');

            return DataTables::eloquent($query)
                ->editColumn('date', fn ($r) => format_date($r->date))
                ->editColumn('return_no', fn ($r) => '<a href="'.route('purchase-returns.show', $r).'" class="font-weight-600">'.e($r->return_no).'</a>')
                ->addColumn('purchase_no', fn ($r) => $r->purchase ? '<a href="'.route('purchases.show', $r->purchase_id).'">'.e($r->purchase->purchase_no).'</a>' : '-')
                ->addColumn('supplier_name', fn ($r) => e($r->supplier->display_name ?? '-'))
                ->editColumn('total', fn ($r) => money($r->total))
                ->editColumn('refund_amount', fn ($r) => $r->refund_amount > 0 ? money($r->refund_amount).' <small class="text-muted">'.e(payment_method_label($r->refund_method)).'</small>' : '-')
                ->editColumn('reason', fn ($r) => e($r->reason ?: '-'))
                ->addColumn('action', fn ($r) => $this->actions([
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('purchase-returns.show', $r)],
                    ['label' => 'Print Debit Note', 'icon' => 'fas fa-print', 'url' => route('purchase-returns.print', $r), 'blank' => true],
                    '-',
                    ['label' => 'Delete', 'delete' => route('purchase-returns.destroy', $r), 'can' => 'purchase_returns.delete', 'message' => 'Returned stock will be added back to inventory.'],
                ]))
                ->filterColumn('supplier_name', fn ($q, $k) => $q->whereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$k}%")->orWhere('company', 'like', "%{$k}%")))
                ->filterColumn('purchase_no', fn ($q, $k) => $q->whereHas('purchase', fn ($s) => $s->where('purchase_no', 'like', "%{$k}%")))
                ->rawColumns(['return_no', 'purchase_no', 'refund_amount', 'action', 'supplier_name', 'reason'])
                ->with('totals', $totals)
                ->make(true);
        }

        return view('purchase-returns.index');
    }

    public function create(Request $request)
    {
        $purchase = $request->purchase_id
            ? Purchase::with(['supplier', 'items.product.unit'])->findOrFail($request->purchase_id)
            : null;

        $factor = $purchase ? $purchase->returnDiscountFactor() : 0;

        return view('purchase-returns.create', compact('purchase', 'factor'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'exists:purchases,id'],
            'date' => ['required', 'date'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', 'in:'.implode(',', array_keys(payment_methods()))],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $purchase = Purchase::findOrFail($data['purchase_id']);
        try {
            $return = $this->service->createReturn($purchase, $data);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('purchase-returns.show', $return)->with('success', "Debit note {$return->return_no} created and stock updated");
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['purchase', 'supplier', 'items.product.unit', 'creator']);

        return view('purchase-returns.show', ['return' => $purchaseReturn]);
    }

    public function print(Request $request, PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['purchase', 'supplier', 'items.product.unit', 'creator']);
        $pdf = Pdf::loadView('pdf.debit-note', ['return' => $purchaseReturn])->setPaper('a4');
        $file = $purchaseReturn->return_no.'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    public function destroy(PurchaseReturn $purchaseReturn)
    {
        try {
            $this->service->deleteReturn($purchaseReturn);
        } catch (ValidationException $e) {
            return $this->failure(collect($e->errors())->flatten()->first());
        }

        return $this->success("Debit note {$purchaseReturn->return_no} deleted", route('purchase-returns.index'));
    }
}
