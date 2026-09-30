<?php

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Database\Migrations\Migration;

/**
 * Every invoice now has its own delivery note. Existing invoices get one (an existing note already linked
 * to the invoice is adopted instead of creating a duplicate).
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(SaleService::class);

        Sale::with(['items', 'customer', 'deliveryPerson'])->orderBy('id')->chunkById(100, function ($sales) use ($service) {
            foreach ($sales as $sale) {
                $service->syncShippingNote($sale);
            }
        });
    }

    public function down(): void
    {
        // Delivery notes are kept.
    }
};
