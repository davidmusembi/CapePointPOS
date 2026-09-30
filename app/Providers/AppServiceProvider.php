<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::morphMap([
            'sale' => Sale::class,
            'sale_return' => SaleReturn::class,
            'purchase' => Purchase::class,
            'purchase_return' => PurchaseReturn::class,
            'purchase_order' => PurchaseOrder::class,
            'delivery_note' => DeliveryNote::class,
            'stock_adjustment' => StockAdjustment::class,
            'payment' => Payment::class,
            'expense' => Expense::class,
            'product' => Product::class,
            'customer' => Customer::class,
            'supplier' => Supplier::class,
        ]);

        // Admin role passes every permission check.
        Gate::before(function ($user) {
            return $user->hasAnyRole(config('pos.super_roles', ['Admin'])) ? true : null;
        });

        Paginator::useBootstrapFour();
    }
}
