<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * In-app notifications for the navbar bell. Only stock alerts (low stock / out of stock) are raised.
 * Failures here never break a business transaction.
 */
class NotificationService
{
    /** Permission required to receive each notification type. */
    public const AUDIENCE = [
        'low_stock' => 'products.view',
        'out_of_stock' => 'products.view',
    ];

    /** Called after every stock movement: alert when a product crosses its alert level or runs out. */
    public function stockChanged(Product $product, float $before): void
    {
        if (! $product->track_stock || ! $product->is_active) {
            return;
        }
        $after = (float) $product->stock_quantity;

        if ($after <= 0 && $before > 0) {
            $this->send($this->outOfStockAlert($product));
        } elseif ($after > 0 && $after <= $product->alert_quantity && $before > $product->alert_quantity) {
            $this->send($this->lowStockAlert($product));
        }
    }

    /**
     * Make sure every product currently at/below its alert level has an (unread) alert.
     * Covers stock that was already low before alerts existed. Throttled unless forced.
     */
    public function syncLowStock(bool $force = false): void
    {
        if (! $force && ! Cache::add('notifications:stock-sync', true, now()->addMinutes(30))) {
            return;
        }

        Product::active()->lowStock()->with('unit')->orderBy('stock_quantity')->limit(500)->get()
            ->each(fn (Product $p) => $this->send($p->stock_quantity <= 0 ? $this->outOfStockAlert($p) : $this->lowStockAlert($p)));
    }

    protected function lowStockAlert(Product $product): SystemAlert
    {
        $unit = $product->unit->short_name ?? '';

        return new SystemAlert(
            "low_stock:{$product->id}", 'low_stock', 'Low stock alert',
            "{$product->name} is down to ".qty_format($product->stock_quantity)." {$unit} (alert level ".qty_format($product->alert_quantity).').',
            route('products.show', $product), 'Inventory'
        );
    }

    protected function outOfStockAlert(Product $product): SystemAlert
    {
        return new SystemAlert(
            "out_of_stock:{$product->id}", 'out_of_stock', 'Out of stock',
            "{$product->name} ({$product->sku}) is out of stock.",
            route('products.show', $product), 'Inventory'
        );
    }

    /**
     * Deliver to every active user allowed to see the notification type.
     * Skipped when the user already has an unread alert with the same key (re-sent once it has been read
     * and the product drops again).
     */
    public function send(SystemAlert $alert): void
    {
        try {
            foreach ($this->audience($alert->type) as $user) {
                if (! $user->unreadNotifications()->where('data->key', $alert->key)->exists()) {
                    $user->notify($alert);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return Collection<int, User> */
    protected function audience(string $type): Collection
    {
        $permission = self::AUDIENCE[$type] ?? null;

        return User::where('is_active', true)->with('roles.permissions', 'permissions')->get()
            ->filter(fn (User $u) => $u->hasAnyRole(config('pos.super_roles', ['Admin'])) || ($permission && $u->hasPermissionTo($permission)));
    }
}
