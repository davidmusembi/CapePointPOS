<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\LedgerService;
use App\Services\ReferenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

/**
 * Shared implementation for customers and suppliers (identical structure, mirrored ledgers).
 */
abstract class ContactController extends Controller implements HasMiddleware
{
    /** 'customer' | 'supplier' */
    protected string $type;

    public static function middleware(): array
    {
        $module = static::MODULE;

        return static::crudPermissions($module, ['view' => ['search', 'statement']]);
    }

    /** @return class-string<Customer|Supplier> */
    protected function model(): string
    {
        return $this->type === 'customer' ? Customer::class : Supplier::class;
    }

    protected function meta(): array
    {
        $isCustomer = $this->type === 'customer';

        return [
            'type' => $this->type,
            'route' => $isCustomer ? 'customers' : 'suppliers',
            'singular' => $isCustomer ? 'Customer' : 'Supplier',
            'plural' => $isCustomer ? 'Customers' : 'Suppliers',
            'perm' => static::MODULE,
            'balanceLabel' => $isCustomer ? 'Receivable' : 'Payable',
            'docLabel' => $isCustomer ? 'Invoiced' : 'Purchased',
        ];
    }

    public function index(Request $request)
    {
        $meta = $this->meta();
        $model = $this->model();

        if ($request->ajax()) {
            $query = $model::query()->withBalance()
                ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
                ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
                ->when($request->balance === 'due', fn ($q) => $q->having('balance', '>', 0.004))
                ->when($request->balance === 'credit', fn ($q) => $q->having('balance', '<', -0.004));

            return DataTables::eloquent($query)
                ->editColumn('name', fn ($c) => '<a href="'.route($meta['route'].'.show', $c).'" class="font-weight-600">'.e($c->name).'</a>'
                    .($c->company ? '<br><small class="text-muted">'.e($c->company).'</small>' : '')
                    .($c->is_active ? '' : ' <span class="badge badge-secondary">Inactive</span>'))
                ->editColumn('total_invoiced', fn ($c) => money($c->total_invoiced))
                ->editColumn('total_paid', fn ($c) => money($c->total_paid))
                ->editColumn('balance', fn ($c) => '<span class="'.($c->balance > 0.004 ? 'amount-positive' : ($c->balance < -0.004 ? 'amount-credit' : '')).'">'.money($c->balance).'</span>')
                ->addColumn('action', fn ($c) => $this->actions([
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route($meta['route'].'.show', $c)],
                    ['label' => 'Statement', 'icon' => 'fas fa-file-alt', 'url' => route($meta['route'].'.statement', $c)],
                    ['label' => $this->type === 'customer' ? 'Receive Payment' : 'Pay Supplier', 'icon' => 'fas fa-money-bill-wave',
                        'url' => route('payments.create', ['type' => $this->type, $this->type.'_id' => $c->id]), 'can' => 'payments.create'],
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'modal' => route($meta['route'].'.edit', $c), 'can' => $meta['perm'].'.edit'],
                    '-',
                    ['label' => 'Delete', 'delete' => route($meta['route'].'.destroy', $c), 'can' => $meta['perm'].'.delete'],
                ]))
                ->filter(function ($q) use ($request) {
                    $term = $request->input('search.value');
                    if ($term) {
                        $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('company', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                    }
                })
                ->rawColumns(['name', 'balance', 'action'])
                ->make(true);
        }

        $totals = $model::query()->withBalance()->get();

        return view('contacts.index', [
            'meta' => $meta,
            'summary' => [
                'count' => $totals->count(),
                'outstanding' => $totals->where('balance', '>', 0)->sum('balance'),
                'with_balance' => $totals->where('balance', '>', 0.004)->count(),
                'credit' => abs($totals->where('balance', '<', 0)->sum('balance')),
            ],
        ]);
    }

    public function create()
    {
        $model = $this->model();

        return view('contacts.form', ['meta' => $this->meta(), 'contact' => new $model([
            'is_active' => true, 'payment_terms' => settings('default_payment_terms'),
        ])]);
    }

    public function store(Request $request)
    {
        $model = $this->model();
        $data = $this->validated($request);
        $data['code'] = ($data['code'] ?? null) ?: ReferenceService::next($this->type);
        $contact = $model::create($data);

        return $this->success($this->meta()['singular'].' added successfully', null, [
            'contact' => ['id' => $contact->id, 'text' => $contact->display_name, 'payment_terms' => $contact->payment_terms],
        ]);
    }

    protected function showContact(Model $contact)
    {
        $meta = $this->meta();
        $contact = $this->model()::query()->withBalance()->findOrFail($contact->id);
        $isCustomer = $this->type === 'customer';

        $documents = $isCustomer
            ? $contact->sales()->latest('date')->latest('id')->take(15)->get()
            : $contact->purchases()->latest('date')->latest('id')->take(15)->get();
        $payments = $contact->payments()->with('allocations')->latest('date')->latest('id')->take(10)->get();
        $table = $isCustomer ? 'sales' : 'purchases';

        $stats = [
            'documents' => DB::table($table)->where($this->type.'_id', $contact->id)->whereNull('deleted_at')->count(),
            'due_on_documents' => (float) DB::table($table)->where($this->type.'_id', $contact->id)->whereNull('deleted_at')->sum('due_amount'),
            'overdue' => (float) DB::table($table)->where($this->type.'_id', $contact->id)->whereNull('deleted_at')
                ->where('due_amount', '>', 0)->whereDate('due_date', '<', now())->sum('due_amount'),
            'last_document' => DB::table($table)->where($this->type.'_id', $contact->id)->whereNull('deleted_at')->max('date'),
        ];

        return view('contacts.show', compact('meta', 'contact', 'documents', 'payments', 'stats'));
    }

    protected function editContact(Model $contact)
    {
        return view('contacts.form', ['meta' => $this->meta(), 'contact' => $contact]);
    }

    protected function updateContact(Request $request, Model $contact)
    {
        $data = $this->validated($request, $contact);
        $data['code'] = ($data['code'] ?? null) ?: $contact->code;
        $contact->update($data);

        return $this->success($this->meta()['singular'].' updated successfully');
    }

    protected function destroyContact(Model $contact)
    {
        $relation = $this->type === 'customer' ? 'sales' : 'purchases';
        if ($contact->{$relation}()->exists() || $contact->payments()->exists()) {
            return $this->failure('This '.strtolower($this->meta()['singular']).' has transactions. Deactivate it instead of deleting.');
        }
        $contact->delete();

        return $this->success($this->meta()['singular'].' deleted');
    }

    /** Select2 search: {results: [{id, text, code, phone, balance, payment_terms}], more} */
    public function search(Request $request)
    {
        $term = trim((string) $request->q);
        $page = max(1, (int) $request->page);
        $rows = $this->model()::query()->withBalance()->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->orderBy('name')->skip(($page - 1) * 20)->take(21)->get();

        return response()->json([
            'results' => $rows->take(20)->map(fn ($c) => [
                'id' => $c->id,
                'text' => $c->display_name,
                'code' => $c->code,
                'phone' => $c->phone,
                'address' => trim($c->address.($c->city ? ', '.$c->city : ''), ', '),
                'balance' => round((float) $c->balance, 2),
                'payment_terms' => $c->payment_terms,
                'credit_limit' => $c->credit_limit ?? null,
            ])->values(),
            'more' => $rows->count() > 20,
        ]);
    }

    protected function statementFor(Request $request, Model $contact)
    {
        [$from, $to] = date_range_from_request($request, 'year');
        $ledger = app(LedgerService::class);
        $statement = $this->type === 'customer'
            ? $ledger->customerStatement($contact, $from, $to)
            : $ledger->supplierStatement($contact, $from, $to);
        $meta = $this->meta();

        if ($request->boolean('pdf')) {
            $pdf = Pdf::loadView('pdf.statement', compact('contact', 'statement', 'meta', 'from', 'to'))->setPaper('a4');
            $name = 'Statement-'.$contact->code.'-'.$to.'.pdf';

            return $request->boolean('download') ? $pdf->download($name) : $pdf->stream($name);
        }

        return view('contacts.statement', compact('contact', 'statement', 'meta', 'from', 'to'));
    }

    protected function validated(Request $request, ?Model $contact = null): array
    {
        $table = $this->type === 'customer' ? 'customers' : 'suppliers';
        $rules = [
            'code' => ['nullable', 'string', 'max:30', Rule::unique($table, 'code')->ignore($contact)],
            'name' => ['required', 'string', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        if ($this->type === 'customer') {
            $rules['credit_limit'] = ['nullable', 'numeric', 'min:0'];
        }
        $data = $request->validate($rules);
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
