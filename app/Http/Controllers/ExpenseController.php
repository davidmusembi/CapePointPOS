<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ReferenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ExpenseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return static::crudPermissions('expenses');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            [$from, $to] = date_range_from_request($request, 'month');
            $base = Expense::query()->whereBetween('date', [$from, $to])
                ->when($request->expense_category_id, fn ($q, $v) => $q->where('expense_category_id', $v))
                ->when($request->payment_method, fn ($q, $v) => $q->where('payment_method', $v));

            $totals = ['amount' => (float) (clone $base)->sum('amount'), 'count' => (clone $base)->count()];

            return DataTables::eloquent((clone $base)->with(['category', 'creator']))
                ->editColumn('date', fn ($e) => format_date($e->date))
                ->addColumn('category_name', fn ($e) => e($e->category->name ?? '-'))
                ->editColumn('amount', fn ($e) => money($e->amount))
                ->editColumn('payment_method', fn ($e) => e(payment_method_label($e->payment_method)))
                ->editColumn('notes', fn ($e) => e(\Illuminate\Support\Str::limit($e->notes, 60)))
                ->addColumn('added_by', fn ($e) => e($e->creator->name ?? '-'))
                ->addColumn('action', fn ($e) => $this->actions([
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'url' => route('expenses.edit', $e), 'can' => 'expenses.edit'],
                    ['label' => 'Delete', 'delete' => route('expenses.destroy', $e), 'can' => 'expenses.delete'],
                ]))
                ->filterColumn('category_name', fn ($q, $k) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$k}%")))
                ->with('totals', $totals)
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('expenses.index', ['categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id')]);
    }

    public function create()
    {
        return view('expenses.form', [
            'expense' => new Expense(['date' => now(), 'payment_method' => 'cash']),
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['reference_no'] = ReferenceService::next('expense');
        $expense = Expense::create($data);

        if ($request->input('submit_action') === 'add_another') {
            return redirect()->route('expenses.create')->with('success', "Expense {$expense->reference_no} recorded");
        }

        return redirect()->route('expenses.index')->with('success', "Expense {$expense->reference_no} recorded (".money($expense->amount).')');
    }

    public function edit(Expense $expense)
    {
        return view('expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validated($request));

        return redirect()->route('expenses.index')->with('success', 'Expense updated');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return $this->success("Expense {$expense->reference_no} deleted");
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(array_keys(payment_methods()))],
            'payee' => ['nullable', 'string', 'max:190'],
            'payment_reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['expense_category_id' => 'category']);
    }
}
