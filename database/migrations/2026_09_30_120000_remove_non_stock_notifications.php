<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The notification bell now only carries stock alerts; drop earlier overdue / payment notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')->get(['id', 'data'])->each(function ($n) {
            $type = json_decode($n->data, true)['type'] ?? null;
            if (! in_array($type, ['low_stock', 'out_of_stock'], true)) {
                DB::table('notifications')->where('id', $n->id)->delete();
            }
        });
    }

    public function down(): void
    {
        // Removed notifications are not restored.
    }
};
