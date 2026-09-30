<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'purchases'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedSmallInteger('payment_terms')->nullable()->after('due_date'); // days
            });
        }

        Schema::table('settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('financial_year_start_month')->default(1)->after('date_format');
            $t->json('enabled_payment_methods')->nullable()->after('allow_negative_stock');
            $t->text('stock_adjustment_reasons')->nullable()->after('enabled_payment_methods');
        });
    }

    public function down(): void
    {
        foreach (['sales', 'purchases'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('payment_terms'));
        }
        Schema::table('settings', fn (Blueprint $t) => $t->dropColumn(['financial_year_start_month', 'enabled_payment_methods', 'stock_adjustment_reasons']));
    }
};
