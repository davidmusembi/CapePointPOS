<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('logo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('tax_label', 30)->default('VAT');
            $table->unsignedBigInteger('default_tax_rate_id')->nullable();
            $table->string('currency_code', 10)->default('KES');
            $table->string('currency_symbol', 10)->default('KSh');
            $table->enum('currency_position', ['before', 'after'])->default('before');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->string('thousand_separator', 3)->default(',');
            $table->string('decimal_separator', 3)->default('.');
            $table->string('date_format', 20)->default('d/m/Y');
            $table->string('invoice_prefix', 20)->default('INV');
            $table->string('credit_note_prefix', 20)->default('CN');
            $table->string('delivery_note_prefix', 20)->default('DN');
            $table->string('lpo_prefix', 20)->default('LPO');
            $table->string('purchase_prefix', 20)->default('PUR');
            $table->string('purchase_return_prefix', 20)->default('DBN');
            $table->string('customer_payment_prefix', 20)->default('RCT');
            $table->string('supplier_payment_prefix', 20)->default('PV');
            $table->string('expense_prefix', 20)->default('EXP');
            $table->string('adjustment_prefix', 20)->default('ADJ');
            $table->unsignedSmallInteger('default_payment_terms')->default(30);
            $table->decimal('default_alert_quantity', 15, 3)->default(5);
            $table->boolean('allow_negative_stock')->default(false);
            $table->text('invoice_terms')->nullable();
            $table->string('invoice_footer')->nullable();
            $table->timestamps();
        });

        Schema::create('reference_counters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['type', 'year']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 8, 3)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20);
            $table->boolean('allow_decimal')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 60)->unique();
            $table->string('barcode', 60)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('cost_price', 15, 4)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('stock_quantity', 15, 3)->default(0);
            $table->decimal('alert_quantity', 15, 3)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // opening, purchase, sale, sale_return, purchase_return, adjustment
            $table->decimal('quantity', 15, 3); // signed: + in, - out
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('balance_after', 15, 3)->default(0);
            $table->nullableMorphs('reference');
            $table->string('reference_no', 60)->nullable();
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'date']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 60)->unique();
            $table->date('date');
            $table->enum('type', ['increase', 'decrease']);
            $table->string('reason')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('reference_counters');
        Schema::dropIfExists('settings');
    }
};
