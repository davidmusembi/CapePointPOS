<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:payments.view', only: ['index', 'show', 'print', 'outstanding']),
            new Middleware('permission:payments.create', only: ['create', 'store']),
            new Middleware('permission:payments.delete', only: ['destroy']),
        ];
    }

    protected function type(Request $request): string
    {
        return $request->input('type') === 'supplier' ? 'supplier' : 'customer';
    }

    public function index(Request $request)
    {
        $type = $this->type($request);

        if ($request->ajax()) {
            [$from, $to] = date_range_from_request($request, 'month');
            $base = Payment::query()->where('party_type', $type)
                ->whereBetween('date', [$from, $to])
                ->when($request->party_id, fn ($q, $v) => $q->where($type.'_id', $v))
                ->when($request->method, fn ($q, $v) => $q->where('method', $v));

            $totals = ['amount' => (float) (clone $base)->sum('amount')];
            $query = (clone $base)->with([$type, 'creator', 'allocations.payable']);

            return DataTables::eloquent($query)
                ->editColumn('date', fn ($p) => format_date($p->date))
                ->editColumn('payment_no', fn ($p) => '<a href="'.route('payments.show', $p).'">'.e($p->payment_no).'</a>')
                ->addColumn('party', function ($p) use ($type) {
                    $party = $p->{$type};

                    return $party ? '<a href="'.route($type === 'customer' ? 'customers.show' : 'suppliers.show', $party).'">'.e($party->display_name).'</a>' : '-';
                })
                ->editColumn('method', fn ($p) => e(payment_method_label($p->method)))
                ->editColumn('amount', fn ($p) => money($p->amount))
                ->addColumn('allocated_to', fn ($p) => $p->allocations->map(fn ($a) => e($a->payable->reference ?? '#').' <small class="text-muted">('.money($a->amount).')</small>')->implode('<br>') ?: '<span class="text-muted">Unallocated</span>')
                ->addColumn('unallocated', fn ($p) => $p->unallocated_amount > 0 ? '<span class="text-warning font-weight-600">'.money($p->unallocated_amount).'</span>' : '-')
                ->addColumn('added_by', fn ($p) => e($p->creator->name ?? '-'))
                ->addColumn('action', fn ($p) => $this->actions([
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('payments.show', $p)],
                    ['label' => 'Print Receipt', 'icon' => 'fas fa-print', 'url' => route('payments.print', $p), 'blank' => true],
                    '-',
                    ['label' => 'Delete', 'delete' => route('payments.destroy', $p), 'can' => 'payments.delete', 'message' => 'The payment and its invoice allocations will be removed.'],
                ]))
                ->filter(function ($q) use ($request, $type) {
                    $term = $request->input('search.value');
                    if ($term) {
                        $q->where(fn ($w) => $w->where('payment_no', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")
                            ->orWhereHas($type, fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('company', 'like', "%{$term}%")));
                    }
                })
                ->with('totals', $totals)
                ->rawColumns(['payment_no', 'party', 'allocated_to', 'unallocated', 'action'])
                ->make(true);
        }

        return view('payments.index', compact('type'));
    }

    public function create(Request $request)
    {
        $type = $this->type($request);

        // Single invoice payment (modal from sales / purchases lists)
        if ($request->filled('sale_id') || $request->filled('purchase_id')) {
            $document = $request->filled('sale_id')
                ? Sale::with('customer')->findOrFail($request->sale_id)
                : Purchase::with('supplier')->findOrFail($request->purchase_id);
            $type = $document instanceof Sale ? 'customer' : 'supplier';

            if ($document->due_amount <= 0) {
                return $request->ajax()
                    ? response('<div class="modal-dialog"><div class="modal-content"><div class="modal-body text-center py-4"><i class="fas fa-check-circle text-success fa-2x mb-2"></i><p class="mb-0">This document is fully paid.</p></div><div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Close</button></div></div></div>')
                    : redirect()->back()->with('info', 'This document is fully paid.');
            }

            return view('payments.modal', compact('document', 'type'));
        }

        $party = null;
        if ($request->filled($type.'_id')) {
            $party = ($type === 'customer' ? Customer::class : Supplier::class)::query()->withBalance()->find($request->input($type.'_id'));
        }

        return view('payments.create', compact('type', 'party'));
    }

    public function store(Request $request, PaymentService $service)
    {
        $type = $request->input('party_type') === 'supplier' ? 'supplier' : 'customer';
        $data = $request->validate([
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
            'customer_id' => ['required_if:party_type,customer', 'nullable', 'exists:customers,id'],
            'supplier_id' => ['required_if:party_type,supplier', 'nullable', 'exists:suppliers,id'],
            'date' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(array_keys(payment_methods()))],
            'reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ], [], ['customer_id' => 'customer', 'supplier_id' => 'supplier']);

        try {
            $payment = $service->record($data, $request->input('allocations', []));
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->withInput()->withErrors($e->errors());
        }

        $message = ($type === 'customer' ? 'Payment received' : 'Supplier payment recorded').' - '.$payment->payment_no.' ('.money($payment->amount).')';

        if ($request->boolean('from_modal')) {
            return $this->success($message, null, ['payment_id' => $payment->id, 'print_url' => route('payments.print', $payment)]);
        }

        return $this->success($message, route('payments.show', $payment));
    }

    public function show(Payment $payment)
    {
        $payment->load(['customer', 'supplier', 'allocations.payable', 'creator']);

        return view('payments.show', compact('payment'));
    }

    public function print(Request $request, Payment $payment)
    {
        $payment->load(['customer', 'supplier', 'allocations.payable', 'creator']);
        $party = $payment->party_type === 'customer'
            ? Customer::query()->withBalance()->find($payment->customer_id)
            : Supplier::query()->withBalance()->find($payment->supplier_id);

        $pdf = Pdf::loadView('pdf.receipt', compact('payment', 'party'))->setPaper('a5', 'portrait');
        $name = $payment->payment_no.'.pdf';

        return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
    }

    public function destroy(Payment $payment, PaymentService $service)
    {
        $type = $payment->party_type;
        $no = $payment->payment_no;
        $service->delete($payment);
        activity('Payment')->log("Payment {$no} deleted");

        return $this->success("Payment {$no} deleted", request()->boolean('redirect') ? route('payments.index', ['type' => $type]) : null);
    }

    /** Outstanding invoices for a party (used by the allocation form). */
    public function outstanding(Request $request)
    {
        $type = $this->type($request);
        $partyId = (int) $request->input('party_id');

        $docs = $type === 'customer'
            ? Sale::where('customer_id', $partyId)->where('due_amount', '>', 0)->orderBy('date')->orderBy('id')->get()
            : Purchase::where('supplier_id', $partyId)->where('due_amount', '>', 0)->orderBy('date')->orderBy('id')->get();

        $party = ($type === 'customer' ? Customer::class : Supplier::class)::query()->withBalance()->find($partyId);

        return response()->json([
            'balance' => round((float) ($party->balance ?? 0), 2),
            'documents' => $docs->map(fn ($d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'supplier_invoice_no' => $d->supplier_invoice_no ?? null,
                'date' => format_date($d->date),
                'due_date' => format_date($d->due_date),
                'overdue' => $d->is_overdue,
                'total' => (float) $d->total,
                'paid' => (float) $d->paid_amount,
                'returned' => (float) $d->returned_amount,
                'due' => (float) $d->due_amount,
                'url' => route($type === 'customer' ? 'sales.show' : 'purchases.show', $d),
            ])->values(),
        ]);
    }
}
