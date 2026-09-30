<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/chart', [DashboardController::class, 'chart'])->name('dashboard.chart');
    Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Attached documents (authorised per owning record inside the controller)
    Route::get('attachments/{attachment}/download', [\App\Http\Controllers\AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [\App\Http\Controllers\AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    /*
    |----------------------------------------------------------------------
    | Settings, users & roles
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::resource('tax-rates', TaxRateController::class)->except('show');
    });

    Route::resource('users', UserController::class)->except('show');
    Route::resource('roles', RoleController::class)->except('show')->middleware('permission:roles.manage');
    Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index')->middleware('permission:activity_log.view');

    /*
    |----------------------------------------------------------------------
    | Inventory
    |----------------------------------------------------------------------
    */
    Route::resource('categories', CategoryController::class)->except('show')->middleware('permission:categories.manage');
    Route::resource('units', UnitController::class)->except('show')->middleware('permission:units.manage');

    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::get('products/{product}/stock-history', [ProductController::class, 'stockHistory'])->name('products.stock-history');
    Route::resource('products', ProductController::class);

    Route::resource('stock-adjustments', StockAdjustmentController::class)->except(['edit', 'update']);

    /*
    |----------------------------------------------------------------------
    | Contacts
    |----------------------------------------------------------------------
    */
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
    Route::resource('customers', CustomerController::class);

    Route::get('suppliers/search', [SupplierController::class, 'search'])->name('suppliers.search');
    Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement'])->name('suppliers.statement');
    Route::resource('suppliers', SupplierController::class);

    /*
    |----------------------------------------------------------------------
    | Sales
    |----------------------------------------------------------------------
    */
    Route::get('sales/due', [SaleController::class, 'due'])->name('sales.due');
    Route::get('sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
    Route::get('sales/{sale}/payments', [SaleController::class, 'payments'])->name('sales.payments');
    Route::resource('sales', SaleController::class);

    Route::get('sale-returns/{sale_return}/print', [SaleReturnController::class, 'print'])->name('sale-returns.print');
    Route::resource('sale-returns', SaleReturnController::class)->except(['edit', 'update']);

    Route::get('delivery-notes/{delivery_note}/print', [DeliveryNoteController::class, 'print'])->name('delivery-notes.print');
    Route::patch('delivery-notes/{delivery_note}/status', [DeliveryNoteController::class, 'updateStatus'])->name('delivery-notes.status');
    // Delivery notes are created from the invoice's shipping section only.
    Route::resource('delivery-notes', DeliveryNoteController::class)->except(['create', 'store']);

    /*
    |----------------------------------------------------------------------
    | Purchases
    |----------------------------------------------------------------------
    */
    Route::get('purchase-orders/{purchase_order}/print', [PurchaseOrderController::class, 'print'])->name('purchase-orders.print');
    Route::patch('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::resource('purchase-orders', PurchaseOrderController::class);

    Route::get('purchases/{purchase}/payments', [PurchaseController::class, 'payments'])->name('purchases.payments');
    Route::resource('purchases', PurchaseController::class);

    Route::get('purchase-returns/{purchase_return}/print', [PurchaseReturnController::class, 'print'])->name('purchase-returns.print');
    Route::resource('purchase-returns', PurchaseReturnController::class)->except(['edit', 'update']);

    /*
    |----------------------------------------------------------------------
    | Expenses & payments
    |----------------------------------------------------------------------
    */
    Route::resource('expense-categories', ExpenseCategoryController::class)->except('show')->middleware('permission:expense_categories.manage');
    Route::resource('expenses', ExpenseController::class)->except('show');

    Route::get('payments/outstanding', [PaymentController::class, 'outstanding'])->name('payments.outstanding');
    Route::get('payments/{payment}/print', [PaymentController::class, 'print'])->name('payments.print');
    Route::resource('payments', PaymentController::class)->except(['edit', 'update']);

    /*
    |----------------------------------------------------------------------
    | Reports
    |----------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->controller(ReportController::class)->group(function () {
        Route::middleware('permission:reports.sales')->group(function () {
            Route::get('purchase-sale', 'purchaseSale')->name('purchase-sale');
            Route::get('sales', 'sales')->name('sales');
            Route::get('sales-due', 'salesDue')->name('sales-due');
            Route::get('aging', 'aging')->name('aging');
            Route::get('sale-returns', 'saleReturns')->name('sale-returns');
        });
        Route::middleware('permission:reports.purchases')->group(function () {
            Route::get('purchases', 'purchases')->name('purchases');
            Route::get('purchase-returns', 'purchaseReturns')->name('purchase-returns');
            Route::get('payables', 'payables')->name('payables');
        });
        Route::get('expenses', 'expenses')->name('expenses')->middleware('permission:reports.expenses');
        Route::get('stock', 'stock')->name('stock')->middleware('permission:reports.inventory');
        Route::get('contacts', 'contacts')->name('contacts')->middleware('permission:reports.contacts');
        Route::get('profit', 'profit')->name('profit')->middleware('permission:reports.profit');
    });
});
