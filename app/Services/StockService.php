<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Move stock for a product and write a stock movement.
     *
     * @param  float  $quantity  signed quantity: positive = stock in, negative = stock out
     * @param  bool  $updateAverageCost  recompute weighted average cost (purchases / stock in with cost)
     */
    public function move(
        Product|int $product,
        float $quantity,
        string $type,
        ?Model $reference = null,
        ?string $referenceNo = null,
        $date = null,
        ?float $unitCost = null,
        ?string $note = null,
        bool $updateAverageCost = false,
    ): ?StockMovement {
        $product = Product::withTrashed()->lockForUpdate()->findOrFail($product instanceof Product ? $product->id : $product);

        if (! $product->track_stock || abs($quantity) < 0.0005) {
            return null;
        }

        $oldQty = (float) $product->stock_quantity;
        $unitCost ??= (float) $product->cost_price;

        if ($updateAverageCost && $quantity > 0 && $unitCost > 0) {
            $baseQty = max(0, $oldQty);
            $product->cost_price = round((($baseQty * $product->cost_price) + ($quantity * $unitCost)) / ($baseQty + $quantity), 4);
        }

        $product->stock_quantity = round($oldQty + $quantity, 3);
        $product->saveQuietly();
        app(NotificationService::class)->stockChanged($product, $oldQty);

        return StockMovement::create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'balance_after' => $product->stock_quantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reference_no' => $referenceNo,
            'date' => $date ?? now()->toDateString(),
            'note' => $note,
        ]);
    }

    /**
     * Undo every stock movement created for a document (used on edit / delete).
     */
    public function reverseFor(Model $reference, bool $enforceAvailability = false): void
    {
        $movements = StockMovement::where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())->get();

        if ($enforceAvailability && ! settings('allow_negative_stock')) {
            foreach ($movements->where('quantity', '>', 0)->groupBy('product_id') as $productId => $rows) {
                $product = Product::withTrashed()->find($productId);
                $qty = $rows->sum('quantity');
                if ($product && $product->track_stock && $product->stock_quantity - $qty < -0.0005) {
                    throw ValidationException::withMessages([
                        'stock' => "Cannot reverse: {$product->name} only has ".qty_format($product->stock_quantity).' in stock ('.qty_format($qty).' required).',
                    ]);
                }
            }
        }

        foreach ($movements as $movement) {
            $product = Product::withTrashed()->lockForUpdate()->find($movement->product_id);
            if ($product) {
                $before = (float) $product->stock_quantity;
                // Undo the weighted-average cost effect of a reversed purchase receipt.
                if ($movement->type === 'purchase' && $movement->quantity > 0) {
                    $remaining = $before - $movement->quantity;
                    if ($before > 0 && $remaining > 0.0005) {
                        $product->cost_price = round(max(0, ($before * $product->cost_price - $movement->quantity * $movement->unit_cost) / $remaining), 4);
                    }
                }
                $product->stock_quantity = round($product->stock_quantity - $movement->quantity, 3);
                $product->saveQuietly();
                app(NotificationService::class)->stockChanged($product, $before);
            }
            $movement->delete();
        }
    }

    /**
     * Validate that enough stock exists for outgoing lines.
     *
     * @param  array<int, float>  $required  product_id => quantity
     */
    public function ensureAvailable(array $required, string $field = 'items'): void
    {
        if (settings('allow_negative_stock')) {
            return;
        }

        // Rows are locked for the rest of the surrounding transaction so concurrent documents can't oversell.
        $products = Product::withTrashed()->whereIn('id', array_keys($required))->lockForUpdate()->get()->keyBy('id');
        $errors = [];
        foreach ($required as $productId => $qty) {
            $product = $products[$productId] ?? null;
            if ($product && $product->track_stock && $product->stock_quantity + 0.0005 < $qty) {
                $errors[] = "{$product->name}: only ".qty_format($product->stock_quantity).' available, '.qty_format($qty).' requested.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages([$field => $errors]);
        }
    }
}
