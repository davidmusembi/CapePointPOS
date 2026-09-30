<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->text('shipping_details')->nullable()->after('notes');
            $t->text('shipping_address')->nullable()->after('shipping_details');
            $t->decimal('shipping_charges', 15, 2)->default(0)->after('shipping_address');
            $t->string('shipping_status', 20)->nullable()->after('shipping_charges');
            $t->string('delivered_to')->nullable()->after('shipping_status'); // received by
            $t->foreignId('delivery_person_id')->nullable()->after('delivered_to')->constrained('users')->nullOnDelete();
            $t->json('additional_charges')->nullable()->after('delivery_person_id');
            $t->decimal('additional_charges_total', 15, 2)->default(0)->after('additional_charges');
            $t->index('shipping_status');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->dropIndex(['shipping_status']);
            $t->dropConstrainedForeignId('delivery_person_id');
            $t->dropColumn(['shipping_details', 'shipping_address', 'shipping_charges', 'shipping_status', 'delivered_to', 'additional_charges', 'additional_charges_total']);
        });
    }
};
