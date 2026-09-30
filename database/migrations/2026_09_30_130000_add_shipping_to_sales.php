<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $t) {
            $t->text('shipping_details')->nullable()->after('notes');
            $t->text('shipping_address')->nullable()->after('shipping_details');
            $t->decimal('shipping_charges', 15, 2)->default(0)->after('shipping_address');
            $t->string('shipping_status', 20)->nullable()->after('shipping_charges');
            $t->string('delivered_to')->nullable()->after('shipping_status');
            $t->foreignId('delivery_person_id')->nullable()->after('delivered_to')->constrained('users')->nullOnDelete();
            $t->json('additional_charges')->nullable()->after('delivery_person_id'); // [{name, amount}]
            $t->decimal('additional_charges_total', 15, 2)->default(0)->after('additional_charges');
            $t->index('shipping_status');
        });

        // Delivery note statuses now share the global shipping status list (config/pos.php).
        Schema::table('delivery_notes', function (Blueprint $t) {
            $t->string('status_new', 20)->default('ordered')->after('status');
            $t->foreignId('delivery_person_id')->nullable()->after('driver_name')->constrained('users')->nullOnDelete();
            $t->boolean('from_sale')->default(false)->after('sale_id'); // created & kept in sync by the sale form
        });
        DB::table('delivery_notes')->update(['status_new' => DB::raw(
            "CASE status WHEN 'pending' THEN 'ordered' WHEN 'dispatched' THEN 'shipped' ELSE status END"
        )]);
        Schema::table('delivery_notes', fn (Blueprint $t) => $t->dropColumn('status'));
        Schema::table('delivery_notes', fn (Blueprint $t) => $t->renameColumn('status_new', 'status'));

        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->morphs('attachable');
            $t->string('category', 40)->default('general'); // e.g. shipping
            $t->string('disk', 20)->default('local');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime_type', 120)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::table('delivery_notes', function (Blueprint $t) {
            $t->dropConstrainedForeignId('delivery_person_id');
            $t->dropColumn('from_sale');
        });
        Schema::table('sales', function (Blueprint $t) {
            $t->dropIndex(['shipping_status']);
            $t->dropConstrainedForeignId('delivery_person_id');
            $t->dropColumn(['shipping_details', 'shipping_address', 'shipping_charges', 'shipping_status', 'delivered_to', 'additional_charges', 'additional_charges_total']);
        });
    }
};
