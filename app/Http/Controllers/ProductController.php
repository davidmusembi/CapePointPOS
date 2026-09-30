<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return array_merge(
            static::crudPermissions('products', ['view' => ['lowStock', 'stockHistory']]),
            [new Middleware('permission:products.view|sales.create|sales.edit|purchases.create|purchases.edit|purchase_orders.create|purchase_orders.edit|stock_adjustments.create|delivery_notes.edit', only: ['search'])],
        );
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Product::query()->with(['category', 'unit', 'taxRate'])
                ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
                ->when($request->unit_id, fn ($q, $v) => $q->where('unit_id', $v))
                ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
                ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
                ->when($request->stock === 'low', fn ($q) => $q->lowStock())
                ->when($request->stock === 'out', fn ($q) => $q->where('track_stock', true)->where('stock_quantity', '<=', 0));

            return DataTables::eloquent($query)
                ->addColumn('category_name', fn ($p) => e($p->category->name ?? '-'))
                ->editColumn('name', fn ($p) => '<a href="'.route('products.show', $p).'" class="font-weight-600">'.e($p->name).'</a>'
                    .($p->is_active ? '' : ' <span class="badge badge-secondary">Inactive</span>'))
                ->editColumn('cost_price', fn ($p) => money($p->cost_price))
                ->editColumn('selling_price', fn ($p) => money($p->selling_price))
                ->addColumn('tax', fn ($p) => $p->taxRate ? e($p->taxRate->name).' ('.(float) $p->taxRate->rate.'%)' : '-')
                ->editColumn('stock_quantity', function ($p) {
                    if (! $p->track_stock) {
                        return '<span class="text-muted">N/A</span>';
                    }
                    $cls = $p->stock_quantity <= 0 ? 'badge-danger' : ($p->is_low_stock ? 'badge-warning' : 'badge-success');

                    return '<span class="badge '.$cls.'">'.qty_format($p->stock_quantity).' '.e($p->unit->short_name ?? '').'</span>';
                })
                ->addColumn('stock_value', fn ($p) => money($p->track_stock ? $p->stock_quantity * $p->cost_price : 0))
                ->addColumn('action', fn ($p) => $this->actions([
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('products.show', $p)],
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'url' => route('products.edit', $p), 'can' => 'products.edit'],
                    ['label' => 'Adjust Stock', 'icon' => 'fas fa-sliders-h', 'url' => route('stock-adjustments.create', ['product_id' => $p->id]), 'can' => 'stock_adjustments.create'],
                    '-',
                    ['label' => 'Delete', 'delete' => route('products.destroy', $p), 'can' => 'products.delete'],
                ]))
                ->filterColumn('category_name', fn ($q, $k) => $q->whereHas('category', fn ($c) => $c->where('name', 'like', "%{$k}%")))
                ->rawColumns(['name', 'stock_quantity', 'action', 'category_name', 'tax'])
                ->make(true);
        }

        return view('products.index', [
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'units' => Unit::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        return view('products.form', $this->formData(new Product([
            'is_active' => true,
            'track_stock' => true,
            'alert_quantity' => settings('default_alert_quantity', 5),
            'tax_rate_id' => settings('default_tax_rate_id'),
        ])));
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request, $stock) {
            $product = Product::create($data);
            $opening = (float) $request->input('opening_stock', 0);
            if ($opening > 0 && $product->track_stock) {
                $stock->move($product, $opening, 'opening', $product, $product->sku, now()->toDateString(), $product->cost_price, 'Opening stock');
            }

            return $product;
        });

        if ($request->input('submit_action') === 'add_another') {
            return redirect()->route('products.create')->with('success', "Product {$product->name} added");
        }

        return redirect()->route('products.index')->with('success', "Product {$product->name} added successfully");
    }

    public function show(Product $product)
    {
        $product->load(['category', 'unit', 'taxRate', 'creator']);

        $stats = [
            'sold' => (float) DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->whereNull('sales.deleted_at')->where('product_id', $product->id)->sum(DB::raw('quantity - returned_quantity')),
            'purchased' => (float) DB::table('purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->whereNull('purchases.deleted_at')->where('product_id', $product->id)->sum(DB::raw('quantity - returned_quantity')),
            'revenue' => (float) DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->whereNull('sales.deleted_at')->where('product_id', $product->id)->sum('net_amount'),
        ];

        return view('products.show', compact('product', 'stats'));
    }

    public function edit(Product $product)
    {
        return view('products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));

        return redirect()->route('products.show', $product)->with('success', 'Product updated successfully');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return $this->success("Product {$product->name} deleted");
    }

    /** Select2 product search used by invoice / purchase / adjustment forms. */
    public function search(Request $request)
    {
        $term = trim((string) $request->q);
        $query = Product::active()->with(['unit', 'taxRate'])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")->orWhere('barcode', $term)))
            ->orderBy('name');

        $page = max(1, (int) $request->page);
        $results = $query->skip(($page - 1) * 20)->take(21)->get();

        return response()->json([
            'results' => $results->take(20)->map(fn (Product $p) => [
                'id' => $p->id,
                'text' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit->short_name ?? '',
                'selling_price' => (float) $p->selling_price,
                'cost_price' => round((float) $p->cost_price, 2),
                'tax_rate' => $p->tax_percent,
                'stock' => (float) $p->stock_quantity,
                'track_stock' => $p->track_stock,
            ])->values(),
            'more' => $results->count() > 20,
        ]);
    }

    public function lowStock(Request $request)
    {
        if ($request->ajax()) {
            $query = Product::active()->lowStock()->with(['category', 'unit']);

            return DataTables::eloquent($query)
                ->addColumn('category_name', fn ($p) => e($p->category->name ?? '-'))
                ->editColumn('name', fn ($p) => '<a href="'.route('products.show', $p).'">'.e($p->name).'</a>')
                ->editColumn('stock_quantity', fn ($p) => '<span class="badge '.($p->stock_quantity <= 0 ? 'badge-danger' : 'badge-warning').'">'.qty_format($p->stock_quantity).' '.e($p->unit->short_name ?? '').'</span>')
                ->editColumn('alert_quantity', fn ($p) => qty_format($p->alert_quantity))
                ->addColumn('shortfall', fn ($p) => qty_format(max(0, $p->alert_quantity - $p->stock_quantity)))
                ->editColumn('cost_price', fn ($p) => money($p->cost_price))
                ->addColumn('action', fn ($p) => $this->actions([
                    ['label' => 'Create LPO', 'icon' => 'fas fa-clipboard-list', 'url' => route('purchase-orders.create', ['product_id' => $p->id]), 'can' => 'purchase_orders.create'],
                    ['label' => 'Add Purchase', 'icon' => 'fas fa-truck-loading', 'url' => route('purchases.create', ['product_id' => $p->id]), 'can' => 'purchases.create'],
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('products.show', $p)],
                ]))
                ->rawColumns(['name', 'stock_quantity', 'action', 'category_name'])
                ->make(true);
        }

        return view('products.low-stock');
    }

    public function stockHistory(Request $request, Product $product)
    {
        $query = StockMovement::query()->where('product_id', $product->id)->with('creator');

        return DataTables::eloquent($query)
            ->editColumn('date', fn ($m) => format_date($m->date))
            ->editColumn('type', fn ($m) => e($m->type_label))
            ->editColumn('reference_no', fn ($m) => e($m->reference_no ?? '-'))
            ->addColumn('qty_in', fn ($m) => $m->quantity > 0 ? '<span class="text-success">+'.qty_format($m->quantity).'</span>' : '')
            ->addColumn('qty_out', fn ($m) => $m->quantity < 0 ? '<span class="text-danger">'.qty_format($m->quantity).'</span>' : '')
            ->editColumn('balance_after', fn ($m) => qty_format($m->balance_after))
            ->editColumn('unit_cost', fn ($m) => money($m->unit_cost))
            ->addColumn('user', fn ($m) => e($m->creator->name ?? '-'))
            ->rawColumns(['qty_in', 'qty_out', 'type', 'reference_no', 'user'])
            ->make(true);
    }

    protected function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'units' => Unit::orderBy('name')->get(),
            'taxRates' => TaxRate::orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:60'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['track_stock'] = $request->boolean('track_stock');
        $data['is_active'] = $request->boolean('is_active');
        $data['alert_quantity'] = $data['alert_quantity'] ?? 0;
        $data['sku'] = $data['sku'] ?: $this->generateSku();
        unset($data['opening_stock']);

        return $data;
    }

    protected function generateSku(): string
    {
        do {
            $sku = 'P'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
