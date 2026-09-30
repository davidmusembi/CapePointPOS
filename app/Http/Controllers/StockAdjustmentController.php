<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\ReferenceService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return static::crudPermissions('stock_adjustments');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            [$from, $to] = date_range_from_request($request, 'year');
            $base = StockAdjustment::query()->whereBetween('date', [$from, $to])
                ->when($request->type, fn ($q, $v) => $q->where('type', $v));
            $totals = [
                'total_amount' => (float) (clone $base)->sum('total_amount'),
            ];

            return DataTables::eloquent((clone $base)->with(['creator'])->withCount('items'))
                ->editColumn('date', fn ($a) => format_date($a->date))
                ->editColumn('reference_no', fn ($a) => '<a href="'.route('stock-adjustments.show', $a).'">'.e($a->reference_no).'</a>')
                ->editColumn('type', fn ($a) => $a->type === 'increase'
                    ? '<span class="badge badge-success"><i class="fas fa-arrow-up"></i> Increase</span>'
                    : '<span class="badge badge-danger"><i class="fas fa-arrow-down"></i> Decrease</span>')
                ->editColumn('total_amount', fn ($a) => money($a->total_amount))
                ->addColumn('added_by', fn ($a) => e($a->creator->name ?? '-'))
                ->addColumn('action', fn ($a) => $this->actions([
                    ['label' => 'View', 'icon' => 'fas fa-eye', 'url' => route('stock-adjustments.show', $a)],
                    ['label' => 'Delete', 'delete' => route('stock-adjustments.destroy', $a), 'can' => 'stock_adjustments.delete', 'message' => 'The stock movement will be reversed.'],
                ]))
                ->with('totals', $totals)
                ->rawColumns(['reference_no', 'type', 'action'])
                ->make(true);
        }

        return view('stock-adjustments.index');
    }

    public function create(Request $request)
    {
        $product = $request->filled('product_id') ? Product::with('unit')->find($request->product_id) : null;

        return view('stock-adjustments.create', ['product' => $product, 'reasons' => stock_adjustment_reasons()]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], ['items.required' => 'Add at least one product.']);

        try {
            $adjustment = DB::transaction(function () use ($data, $stock) {
                $required = [];
                foreach ($data['items'] as $row) {
                    $required[$row['product_id']] = ($required[$row['product_id']] ?? 0) + (float) $row['quantity'];
                }
                if ($data['type'] === 'decrease') {
                    $stock->ensureAvailable($required);
                }

                $adjustment = StockAdjustment::create([
                    'reference_no' => ReferenceService::next('adjustment'),
                    'date' => $data['date'],
                    'type' => $data['type'],
                    'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null,
                ]);

                $total = 0;
                $products = Product::whereIn('id', array_keys($required))->get()->keyBy('id');
                foreach ($data['items'] as $row) {
                    $product = $products[$row['product_id']];
                    $qty = round((float) $row['quantity'], 3);
                    // Adjustments are valued at the current weighted-average cost and never change it.
                    $cost = (float) $product->cost_price;
                    $subtotal = round($qty * $cost, 2);
                    $adjustment->items()->create(['product_id' => $product->id, 'quantity' => $qty, 'unit_cost' => $cost, 'subtotal' => $subtotal]);
                    $stock->move($product, $data['type'] === 'increase' ? $qty : -$qty, 'adjustment', $adjustment,
                        $adjustment->reference_no, $data['date'], $cost, $data['reason']);
                    $total += $subtotal;
                }
                $adjustment->update(['total_amount' => round($total, 2)]);

                return $adjustment;
            });
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('stock-adjustments.show', $adjustment)->with('success', "Stock adjustment {$adjustment->reference_no} saved");
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load(['items.product.unit', 'creator']);

        return view('stock-adjustments.show', ['adjustment' => $stockAdjustment]);
    }

    public function destroy(StockAdjustment $stockAdjustment, StockService $stock)
    {
        try {
            DB::transaction(function () use ($stockAdjustment, $stock) {
                $stock->reverseFor($stockAdjustment, true);
                $stockAdjustment->delete();
            });
        } catch (ValidationException $e) {
            return $this->failure(collect($e->errors())->flatten()->first());
        }

        return $this->success("Adjustment {$stockAdjustment->reference_no} deleted and stock reversed");
    }
}
