<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SaleController extends Controller implements HasMiddleware
{
    public function __construct(protected SaleService $service) {}

    public static function middleware(): array
    {
        return static::crudPermissions('sales', ['view' => ['print', 'due', 'payments']]);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->table($request);
        }

        return view('sales.index');
    }

    public function due(Request $request)
    {
        if ($request->ajax()) {
            return $this->table($request, true);
        }

        $base = Sale::where('due_amount', '>', 0);
        $summary = [
            'total_due' => (float) (clone $base)->sum('due_amount'),
            'overdue' => (float) (clone $base)->whereNotNull('due_date')->where('due_date', '<', now()->toDateString())->sum('due_amount'),
            'count' => (clone $base)->count(),
            'customers' => (clone $base)->distinct('customer_id')->count('customer_id'),
        ];

        return view('sales.due', compact('summary'));
    }

    protected function filteredQuery(Request $request, bool $dueOnly = false)
    {
        $query = Sale::query()->select('sales.*');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sales.date', [$request->start_date, $request->end_date]);
        }

        return $query
            ->when($request->customer_id, fn ($q, $v) => $q->where('sales.customer_id', $v))
            ->when($request->payment_status, fn ($q, $v) => $q->where('sales.payment_status', $v))
            ->when($request->shipping_status, fn ($q, $v) => $v === 'none' ? $q->whereNull('sales.shipping_status') : $q->where('sales.shipping_status', $v))
            ->when($request->boolean('overdue'), fn ($q) => $q->where('sales.due_amount', '>', 0)
                ->whereNotNull('sales.due_date')->where('sales.due_date', '<', now()->toDateString()))
            ->when($dueOnly, fn ($q) => $q->where('sales.due_amount', '>', 0));
    }

    protected function table(Request $request, bool $dueOnly = false)
    {
        $query = $this->filteredQuery($request, $dueOnly);
        $totalsQuery = clone $query;
        $totals = [
            'total' => (float) (clone $totalsQuery)->sum('total'),
            'paid' => (float) (clone $totalsQuery)->sum('paid_amount'),
            'returned' => (float) (clone $totalsQuery)->sum('returned_amount'),
            'due' => (float) (clone $totalsQuery)->sum('due_amount'),
        ];

        $query->with(['customer', 'creator'])->withCount('returns');
        $today = now()->startOfDay();

        return DataTables::eloquent($query)
            ->editColumn('date', fn ($s) => format_date($s->date))
            ->editColumn('invoice_no', fn ($s) => '<a href="'.route('sales.show', $s).'" class="font-weight-600">'.e($s->invoice_no).'</a>')
            ->addColumn('customer_name', fn ($s) => e($s->customer->display_name ?? '-'))
            ->editColumn('total', fn ($s) => money($s->total))
            ->editColumn('paid_amount', fn ($s) => money($s->paid_amount))
            ->editColumn('returned_amount', fn ($s) => $s->returned_amount > 0 ? money($s->returned_amount) : '-')
            ->editColumn('due_amount', fn ($s) => $s->due_amount > 0 ? '<span class="text-danger font-weight-600">'.money($s->due_amount).'</span>' : money(0))
            ->editColumn('payment_status', fn ($s) => payment_status_badge($s->payment_status))
            ->editColumn('shipping_status', fn ($s) => shipping_status_badge($s->shipping_status))
            ->editColumn('due_date', fn ($s) => $s->due_date
                ? ($s->is_overdue ? '<span class="text-danger font-weight-600" title="Overdue">'.format_date($s->due_date).'</span>' : format_date($s->due_date))
                : '-')
            ->addColumn('days_overdue', function ($s) use ($today) {
                if (! $s->is_overdue) {
                    return '<span class="text-muted">-</span>';
                }
                $days = (int) $s->due_date->diffInDays($today);

                return '<span class="badge '.($days > 60 ? 'badge-danger' : ($days > 30 ? 'badge-warning' : 'badge-secondary')).'">'.$days.' days</span>';
            })
            ->addColumn('added_by', fn ($s) => e($s->creator->name ?? '-'))
            ->addColumn('action', fn ($s) => $this->actions([
                ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('sales.show', $s)],
                $s->returns_count ? '-' : ['label' => 'Edit', 'icon' => 'fas fa-edit', 'url' => route('sales.edit', $s), 'can' => 'sales.edit'],
                ['label' => 'Print Invoice', 'icon' => 'fas fa-print', 'url' => route('sales.print', $s), 'blank' => true],
                ['label' => 'Download PDF', 'icon' => 'far fa-file-pdf', 'url' => route('sales.print', [$s, 'download' => 1])],
                '-',
                $s->due_amount > 0 ? ['label' => 'Add Payment', 'icon' => 'fas fa-money-bill-wave', 'modal' => route('payments.create', ['type' => 'customer', 'sale_id' => $s->id]), 'can' => 'payments.create'] : '-',
                ['label' => 'View Payments', 'icon' => 'fas fa-list-alt', 'modal' => route('sales.payments', $s)],
                '-',
                ['label' => 'Sales Return', 'icon' => 'fas fa-undo-alt', 'url' => route('sale-returns.create', ['sale_id' => $s->id]), 'can' => 'sale_returns.create'],
                ['label' => 'Create Delivery Note', 'icon' => 'fas fa-shipping-fast', 'url' => route('delivery-notes.create', ['sale_id' => $s->id]), 'can' => 'delivery_notes.create'],
                '-',
                ['label' => 'Delete', 'delete' => route('sales.destroy', $s), 'can' => 'sales.delete', 'message' => 'The invoice will be deleted and its stock restored.'],
            ]))
            ->filterColumn('customer_name', fn ($q, $k) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$k}%")->orWhere('company', 'like', "%{$k}%")))
            ->with('totals', $totals)
            ->rawColumns(['invoice_no', 'due_amount', 'payment_status', 'shipping_status', 'due_date', 'days_overdue', 'action'])
            ->make(true);
    }

    public function create(Request $request)
    {
        $customer = $request->customer_id ? Customer::find($request->customer_id) : null;

        return view('sales.form', [
            'sale' => new Sale(['date' => now(), 'discount_type' => 'fixed', 'discount_value' => 0]),
            'customer' => $customer,
            'items' => [],
            'taxRates' => $this->taxRates(),
            'deliveryPeople' => $this->deliveryPeople(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            $sale = $this->service->create($data);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        app(AttachmentService::class)->storeMany($sale, $request->file('shipping_documents', []), 'shipping');

        return $this->redirectAfterSave($request, $sale, "Invoice {$sale->invoice_no} created successfully");
    }

    public function show(Request $request, Sale $sale)
    {
        $sale->load(['customer', 'creator', 'items.product.unit', 'allocations.payment', 'returns', 'deliveryNotes', 'deliveryPerson', 'attachments.uploader']);

        return view('sales.show', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        if ($sale->returns()->exists()) {
            return redirect()->route('sales.show', $sale)->with('error', 'This invoice has returns (credit notes) and can no longer be edited.');
        }

        $sale->load(['customer', 'items.product.unit', 'attachments']);
        $items = $sale->items->map(fn ($i) => [
            'id' => $i->product_id,
            'text' => $i->product->name ?? 'Deleted product',
            'sku' => $i->product->sku ?? '',
            'unit' => $i->product->unit->short_name ?? '',
            'stock' => (float) ($i->product->stock_quantity ?? 0) + $i->quantity,
            'track_stock' => (bool) ($i->product->track_stock ?? false),
            'price' => $i->unit_price,
            'quantity' => $i->quantity,
            'discount_percent' => $i->discount_percent,
            'tax_rate_line' => $i->tax_rate,
            'description' => $i->description,
        ])->values()->all();

        return view('sales.form', [
            'sale' => $sale,
            'customer' => $sale->customer,
            'items' => $items,
            'taxRates' => $this->taxRates(),
            'deliveryPeople' => $this->deliveryPeople($sale->delivery_person_id),
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        $data = $this->validated($request);

        try {
            $sale = $this->service->update($sale, $data);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        app(AttachmentService::class)->storeMany($sale, $request->file('shipping_documents', []), 'shipping');

        return $this->redirectAfterSave($request, $sale, "Invoice {$sale->invoice_no} updated successfully");
    }

    public function destroy(Sale $sale)
    {
        try {
            $this->service->delete($sale);
        } catch (ValidationException $e) {
            return $this->failure(collect($e->errors())->flatten()->first());
        }

        return $this->success("Invoice {$sale->invoice_no} deleted", route('sales.index'));
    }

    public function print(Request $request, Sale $sale)
    {
        $sale->load(['customer', 'items.product.unit', 'allocations.payment', 'creator', 'deliveryPerson']);
        $pdf = Pdf::loadView('pdf.invoice', compact('sale'))->setPaper('a4');
        $file = 'Invoice-'.$sale->invoice_no.'.pdf';

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    public function payments(Sale $sale)
    {
        $sale->load(['allocations.payment.creator', 'customer']);

        return view('sales.payments', compact('sale'));
    }

    protected function redirectAfterSave(Request $request, Sale $sale, string $message)
    {
        $params = $request->input('submit_action') === 'print' ? [$sale, 'print' => 1] : [$sale];

        return redirect()->route('sales.show', $params)->with('success', $message);
    }

    /** Active users who can be assigned deliveries (keeps a deactivated current assignee selectable). */
    protected function deliveryPeople(?int $current = null)
    {
        return User::where(fn ($q) => $q->where('is_active', true)->when($current, fn ($w) => $w->orWhere('id', $current)))
            ->orderBy('name')->pluck('name', 'id');
    }

    protected function taxRates(): array
    {
        return TaxRate::orderBy('name')->get()->map(fn ($t) => ['rate' => (float) $t->rate, 'name' => $t->name])->all();
    }

    protected function validated(Request $request): array
    {
        return $request->validate(\App\Services\PaymentService::rowRules() + [
            'customer_id' => ['required', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'customer_reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping_details' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'shipping_charges' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'shipping_status' => ['nullable', Rule::in(array_keys(shipping_statuses()))],
            'delivered_to' => ['nullable', 'string', 'max:190'],
            'delivery_person_id' => ['nullable', Rule::exists('users', 'id')],
            'additional_charges' => ['nullable', 'array', 'max:20'],
            'additional_charges.*.name' => ['nullable', 'string', 'max:120'],
            'additional_charges.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'shipping_documents' => ['nullable', 'array', 'max:'.(int) config('pos.attachments.max_files', 10)],
            'shipping_documents.*' => AttachmentService::rules(),
        ], [
            'shipping_documents.*.mimes' => 'Shipping documents must be one of: '.implode(', ', config('pos.attachments.mimes')).'.',
            'shipping_documents.*.max' => 'Each shipping document may not be larger than '.round(config('pos.attachments.max_kb') / 1024).' MB.',
            'items.required' => 'Add at least one product to the invoice.',
        ]);
    }
}
