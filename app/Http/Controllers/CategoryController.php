<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Category::query()->withCount('products');

            return DataTables::eloquent($query)
                ->addColumn('action', fn (Category $c) => $this->actions([
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'modal' => route('categories.edit', $c)],
                    ['label' => 'Delete', 'delete' => route('categories.destroy', $c)],
                ]))
                ->editColumn('description', fn ($c) => e(\Illuminate\Support\Str::limit($c->description, 80)))
                ->rawColumns(['action', 'description'])
                ->make(true);
        }

        return view('categories.index');
    }

    public function create()
    {
        return view('categories.form', ['category' => new Category]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));

        return $this->success('Category added successfully');
    }

    public function edit(Category $category)
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));

        return $this->success('Category updated successfully');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return $this->failure('This category has products assigned. Move them before deleting.');
        }
        $category->delete();

        return $this->success('Category deleted successfully');
    }

    protected function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('categories')->ignore($category)->whereNull('deleted_at')],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
