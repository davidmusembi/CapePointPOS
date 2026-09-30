<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Must hold the invoice's multi-line shipping address.
        Schema::table('delivery_notes', fn (Blueprint $t) => $t->text('delivery_address')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('delivery_notes', fn (Blueprint $t) => $t->string('delivery_address')->nullable()->change());
    }
};
