<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Creates in-app notifications for the navbar bell. Failures here never break a business transaction.
 */
class NotificationService
{
    /** Permission required to receive each notification type. */
    public const AUDIENCE = [
        'low_stock' => 'products.view',
        'out_of_stock' => 'products.view',
        'invoice_overdue' => 'sales.view',
        'bill_overdue' => 'purchases.view',
        'payment_received' => 'payments.view',
    ];

    /** Called after every stock movement: alert when a product crosses its alert level or runs out. */
    public function stockChanged(Product $product, float $before): void
    {
        if (! $product->track_stock || ! $product->is_active) {
            return;
        }
        $after = (float) $product->stock_quantity;
        $unit = $product->unit->short_name ?? '';

        if ($after <= 0 && $before > 0) {
            $this->send(new SystemAlert(
                "out_of_stock:{$product->id}", 'out_of_stock', 'Out of stock',
                "{$product->name} ({$product->sku}) is out of stock.",
                route('products.show', $product), 'Inventory'
            ), resendAfterRead: true);
        } elseif ($after > 0 && $after <= $product->alert_quantity && $before > $product->alert_quantity) {
            $this->send(new SystemAlert(
                "low_stock:{$product->id}", 'low_stock', 'Low stock alert',
                "{$product->name} is down to ".qty_format($after)." {$unit} (alert level ".qty_format($product->alert_quantity).').',
                route('products.show', $product), 'Inventory'
            ), resendAfterRead: true);
        }
    }

    public function paymentReceived(Payment $payment): void
    {
        if ($payment->party_type !== 'customer') {
            return;
        }
        $customer = $payment->customer;
        $this->send(new SystemAlert(
            "payment:{$payment->id}", 'payment_received', 'Payment received',
            money($payment->amount).' from '.($customer?->display_name ?? 'customer').' via '.payment_method_label($payment->method).'.',
            route('payments.show', $payment), $payment->payment_no
        ), except: $payment->created_by);
    }

    /** Overdue invoices / supplier bills - each document is announced once. Throttled. */
    public function syncOverdue(bool $force = false): void
    {
        if (! $force && ! Cache::add('notifications:overdue-sync', true, now()->addMinutes(30))) {
            return;
        }
        $today = now()->toDateString();

        Sale::with('customer')->where('due_amount', '>', 0)->whereNotNull('due_date')->where('due_date', '<', $today)
            ->orderBy('due_date')->limit(200)->get()->each(function (Sale $s) {
                $this->send(new SystemAlert(
                    "invoice_overdue:{$s->id}", 'invoice_overdue', 'Invoice overdue',
                    "{$s->invoice_no} for ".($s->customer?->display_name ?? '-').' is overdue by '.(int) $s->due_date->diffInDays(now()).' days - '.money($s->due_amount).' due.',
                    route('sales.show', $s), 'Receivables'
                ));
            });

        Purchase::with('supplier')->where('due_amount', '>', 0)->whereNotNull('due_date')->where('due_date', '<', $today)
            ->orderBy('due_date')->limit(200)->get()->each(function (Purchase $p) {
                $this->send(new SystemAlert(
                    "bill_overdue:{$p->id}", 'bill_overdue', 'Supplier bill overdue',
                    "{$p->purchase_no} from ".($p->supplier?->display_name ?? '-').' is overdue - '.money($p->due_amount).' payable.',
                    route('purchases.show', $p), 'Payables'
                ));
            });
    }

    /**
     * Deliver to every active user allowed to see the notification type.
     * De-duplicated by key: skipped if the user already has it (unread, or ever when $resendAfterRead is false).
     */
    public function send(SystemAlert $alert, bool $resendAfterRead = false, ?int $except = null): void
    {
        try {
            foreach ($this->audience($alert->type) as $user) {
                if ($except && $user->id === $except) {
                    continue;
                }
                $existing = $user->notifications()->where('data->key', $alert->key);
                if ($resendAfterRead) {
                    $existing->whereNull('read_at');
                }
                if (! $existing->exists()) {
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
