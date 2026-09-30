<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ExpenseCategory::query()->withCount('expenses')->withSum('expenses', 'amount');

            return DataTables::eloquent($query)
                ->editColumn('expenses_sum_amount', fn ($c) => money($c->expenses_sum_amount ?? 0))
                ->addColumn('action', fn (ExpenseCategory $c) => $this->actions([
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'modal' => route('expense-categories.edit', $c)],
                    ['label' => 'Delete', 'delete' => route('expense-categories.destroy', $c)],
                ]))
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('expense-categories.index');
    }

    public function create()
    {
        return view('expense-categories.form', ['category' => new ExpenseCategory]);
    }

    public function store(Request $request)
    {
        $category = ExpenseCategory::create($this->validated($request));

        return $this->success('Expense category added', null, ['category' => ['id' => $category->id, 'text' => $category->name]]);
    }

    public function edit(ExpenseCategory $expenseCategory)
    {
        return view('expense-categories.form', ['category' => $expenseCategory]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($this->validated($request, $expenseCategory));

        return $this->success('Expense category updated');
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->expenses()->exists()) {
            return $this->failure('This category has expenses recorded and cannot be deleted.');
        }
        $expenseCategory->delete();

        return $this->success('Expense category deleted');
    }

    protected function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('expense_categories')->ignore($category)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
