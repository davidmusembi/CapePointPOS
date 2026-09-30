<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(Unit::query()->withCount('products'))
                ->editColumn('allow_decimal', fn ($u) => $u->allow_decimal ? 'Yes' : 'No')
                ->addColumn('action', fn (Unit $u) => $this->actions([
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'modal' => route('units.edit', $u)],
                    ['label' => 'Delete', 'delete' => route('units.destroy', $u)],
                ]))
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('units.index');
    }

    public function create()
    {
        return view('units.form', ['unit' => new Unit]);
    }

    public function store(Request $request)
    {
        Unit::create($this->validated($request));

        return $this->success('Unit added successfully');
    }

    public function edit(Unit $unit)
    {
        return view('units.form', compact('unit'));
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($this->validated($request, $unit));

        return $this->success('Unit updated successfully');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->exists()) {
            return $this->failure('This unit is used by products and cannot be deleted.');
        }
        $unit->delete();

        return $this->success('Unit deleted successfully');
    }

    protected function validated(Request $request, ?Unit $unit = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('units')->ignore($unit)->whereNull('deleted_at')],
            'short_name' => ['required', 'string', 'max:20'],
        ]);
        $data['allow_decimal'] = $request->boolean('allow_decimal');

        return $data;
    }
}
