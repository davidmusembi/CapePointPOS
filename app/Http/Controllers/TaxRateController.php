<?php

namespace App\Http\Controllers;

use App\Models\TaxRate;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class TaxRateController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(TaxRate::query()->withCount('products'))
                ->editColumn('rate', fn ($t) => (float) $t->rate.'%')
                ->addColumn('is_default', fn ($t) => settings('default_tax_rate_id') == $t->id ? '<span class="badge badge-primary">Default</span>' : '')
                ->addColumn('action', fn (TaxRate $t) => $this->actions([
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'modal' => route('tax-rates.edit', $t)],
                    ['label' => 'Delete', 'delete' => route('tax-rates.destroy', $t)],
                ]))
                ->rawColumns(['is_default', 'action'])
                ->make(true);
        }

        return view('tax-rates.index');
    }

    public function create()
    {
        return view('tax-rates.form', ['taxRate' => new TaxRate]);
    }

    public function store(Request $request)
    {
        TaxRate::create($this->validated($request));

        return $this->success('Tax rate added successfully');
    }

    public function edit(TaxRate $taxRate)
    {
        return view('tax-rates.form', compact('taxRate'));
    }

    public function update(Request $request, TaxRate $taxRate)
    {
        $taxRate->update($this->validated($request));

        return $this->success('Tax rate updated. Existing documents keep the rate they were created with.');
    }

    public function destroy(TaxRate $taxRate)
    {
        if ($taxRate->products()->exists()) {
            return $this->failure('This tax rate is assigned to products. Change them first.');
        }
        if (settings('default_tax_rate_id') == $taxRate->id) {
            return $this->failure('This is the default tax rate. Choose another default in business settings first.');
        }
        $taxRate->delete();

        return $this->success('Tax rate deleted');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
