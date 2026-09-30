<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\ReferenceService;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic demo data for the last ~4 months: catalogue, contacts, LPOs, purchases, sales with mixed
 * payment states (so due / aging reports populate), returns, delivery notes, payments and expenses.
 */
class DemoDataSeeder extends Seeder
{
    public function run(StockService $stock, SaleService $sales, PurchaseService $purchases, PaymentService $payments): void
    {
        if (Product::exists()) {
            $this->command?->warn('Demo data already present - skipping.');

            return;
        }

        mt_srand(2026);
        Auth::setUser(User::where('email', 'admin@capepoint.test')->first());

        $vat = TaxRate::where('name', 'VAT')->value('id');
        $units = Unit::pluck('id', 'short_name');
        $cats = Category::pluck('id', 'code');

        /* ---------------- Products ---------------- */
        $catalogue = [
            ['HP LaserJet Pro M404dn Printer', 'ELEC', 'Pcs', 32000, 41500, $vat, 12],
            ['Dell Latitude 5440 Laptop i5', 'ELEC', 'Pcs', 78000, 96500, $vat, 8],
            ['Logitech MK270 Wireless Keyboard & Mouse', 'ELEC', 'Pcs', 2300, 3450, $vat, 40],
            ['Samsung 24" LED Monitor', 'ELEC', 'Pcs', 13500, 17900, $vat, 15],
            ['APC 1200VA UPS', 'ELEC', 'Pcs', 11800, 15500, $vat, 10],
            ['Kingston 32GB USB Flash Drive', 'ELEC', 'Pcs', 650, 1100, $vat, 80],
            ['HP 26A Toner Cartridge', 'ELEC', 'Pcs', 6800, 9200, $vat, 20],
            ['A4 Copy Paper 80gsm (Ream)', 'STAT', 'Ream', 520, 750, $vat, 150],
            ['Box File Heavy Duty', 'STAT', 'Pcs', 180, 320, $vat, 60],
            ['BIC Ballpoint Pens Blue (Box of 50)', 'STAT', 'Box', 750, 1150, $vat, 25],
            ['Spring Notebook A5 200pg', 'STAT', 'Pcs', 140, 250, $vat, 100],
            ['Stapler Heavy Duty', 'STAT', 'Pcs', 850, 1400, $vat, 20],
            ['Executive Office Chair (Mesh)', 'FURN', 'Pcs', 9800, 14500, $vat, 6],
            ['Office Desk 1.4m', 'FURN', 'Pcs', 14500, 21000, $vat, 4],
            ['4-Drawer Steel Filing Cabinet', 'FURN', 'Pcs', 16500, 23500, $vat, 3],
            ['Visitor Chair (Padded)', 'FURN', 'Pcs', 3900, 5800, $vat, 10],
            ['TP-Link 24-Port Gigabit Switch', 'NET', 'Pcs', 14200, 18900, $vat, 5],
            ['Cat6 UTP Cable (305m Box)', 'NET', 'Box', 11500, 15800, $vat, 6],
            ['RJ45 Connectors (Pack of 100)', 'NET', 'Pack', 750, 1250, $vat, 20],
            ['Ubiquiti UniFi AP AC Lite', 'NET', 'Pcs', 10200, 13900, $vat, 6],
            ['Liquid Hand Soap 5L', 'CLN', 'Ltr', 180, 290, $vat, 50],
            ['Toilet Tissue (Pack of 10)', 'CLN', 'Pack', 420, 650, $vat, 40],
            ['Floor Detergent 20L', 'CLN', 'Pcs', 2200, 3200, $vat, 10],
            ['Kenya AA Tea Leaves 500g', 'BEV', 'Pcs', 260, 390, null, 40],
            ['Instant Coffee 200g', 'BEV', 'Pcs', 590, 850, null, 30],
            ['Bottled Water 500ml (Carton of 24)', 'BEV', 'Ctn', 380, 560, $vat, 40],
            ['Network Installation (per point)', 'SRV', 'Svc', 0, 3500, $vat, 0],
            ['Printer Servicing', 'SRV', 'Svc', 0, 4500, $vat, 0],
        ];

        $products = collect();
        foreach ($catalogue as $i => [$name, $cat, $unit, $cost, $price, $tax, $alert]) {
            $isService = $cat === 'SRV';
            $product = Product::create([
                'name' => $name,
                'sku' => sprintf('%s-%04d', $cat, $i + 1),
                'barcode' => $isService ? null : (string) (6161100000000 + $i * 37),
                'category_id' => $cats[$cat] ?? null,
                'unit_id' => $units[$unit] ?? $units['Pcs'],
                'tax_rate_id' => $tax,
                'cost_price' => $cost,
                'selling_price' => $price,
                'alert_quantity' => $alert,
                'track_stock' => ! $isService,
                'is_active' => true,
            ]);
            if (! $isService) {
                $openingQty = max($alert * 2, (int) round($alert * (mt_rand(15, 40) / 10))) ;
                // a few items deliberately start low so low-stock alerts have content
                if (in_array($i, [4, 14, 19], true)) {
                    $openingQty = max(1, (int) floor($alert / 2));
                }
                $stock->move($product, $openingQty, 'opening', $product, $product->sku, now()->subDays(130)->toDateString(), $cost, 'Opening stock');
            }
            $products->push($product->fresh());
        }
        $stocked = $products->where('track_stock', true)->values();

        /* ---------------- Contacts ---------------- */
        $customerRows = [
            ['Wanjiku Kamau', 'Kamau & Sons Hardware', 'Nairobi', 100000, 30], ['James Otieno', 'Lakeside Schools Ltd', 'Kisumu', 250000, 30],
            ['Aisha Mohamed', 'Coastline Logistics', 'Mombasa', 300000, 45], ['Peter Mwangi', 'Highlands Dairy Co-op', 'Nyeri', 150000, 30],
            ['Grace Njeri', 'Njeri Pharmacy', 'Thika', 80000, 14], ['Brian Kiprono', 'Rift Valley Agrovet', 'Eldoret', 120000, 30],
            ['Faith Achieng', 'Achieng Consulting', 'Nairobi', 60000, 15], ['Daniel Mutua', 'Machakos County Hospital', 'Machakos', 500000, 60],
            ['Samuel Kariuki', 'Kariuki Supermarket', 'Nakuru', 200000, 30], ['Mercy Wambui', null, 'Nairobi', null, 7],
            ['Omar Hassan', 'Hassan Travel Agency', 'Mombasa', 90000, 30], ['Lucy Chebet', 'Chebet Academy', 'Kericho', 150000, 30],
        ];
        $customers = collect();
        foreach ($customerRows as $i => [$name, $company, $city, $limit, $terms]) {
            $customers->push(Customer::create([
                'code' => ReferenceService::next('customer'),
                'name' => $name,
                'company' => $company,
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.co.ke',
                'phone' => '+2547'.mt_rand(10, 99).mt_rand(100000, 999999),
                'address' => mt_rand(1, 400).' '.['Moi Avenue', 'Kenyatta Road', 'Oginga Odinga St', 'Ngong Road', 'Digo Road'][$i % 5],
                'city' => $city,
                'tax_number' => $company ? 'P05'.mt_rand(1000000, 9999999).'K' : null,
                'credit_limit' => $limit,
                'opening_balance' => $i === 1 ? 45000 : ($i === 7 ? 120000 : 0),
                'payment_terms' => $terms,
                'is_active' => true,
            ]));
        }

        $supplierRows = [
            ['Techsource Distributors', 'Nairobi', 30], ['Office Mart Wholesalers', 'Nairobi', 30], ['Mombasa Furniture Works', 'Mombasa', 45],
            ['NetLink East Africa', 'Nairobi', 30], ['CleanPro Supplies', 'Thika', 14], ['Highland Beverages Ltd', 'Nakuru', 30],
            ['PaperWorld Kenya', 'Nairobi', 30], ['Digital Imaging Ltd', 'Nairobi', 60],
        ];
        $suppliers = collect();
        foreach ($supplierRows as $i => [$company, $city, $terms]) {
            $suppliers->push(Supplier::create([
                'code' => ReferenceService::next('supplier'),
                'name' => ['John Maina', 'Esther Wairimu', 'Ali Bakari', 'Kevin Ochieng', 'Ruth Nyambura', 'Paul Rotich', 'Joyce Akinyi', 'Tom Kimani'][$i],
                'company' => $company,
                'email' => 'sales@'.strtolower(str_replace(' ', '', explode(' ', $company)[0])).'.co.ke',
                'phone' => '+2547'.mt_rand(10, 99).mt_rand(100000, 999999),
                'address' => 'Industrial Area, Plot '.mt_rand(10, 300),
                'city' => $city,
                'tax_number' => 'P05'.mt_rand(1000000, 9999999).'S',
                'opening_balance' => $i === 0 ? 85000 : 0,
                'payment_terms' => $terms,
                'is_active' => true,
            ]));
        }

        $methods = ['cash', 'bank', 'mobile_money', 'mobile_money', 'cheque'];
        $pick = fn ($collection, $n) => $collection->shuffle()->take($n)->values();

        /* ---------------- LPOs & purchases ---------------- */
        for ($i = 0; $i < 6; $i++) {
            $date = now()->subDays(110 - $i * 18);
            $supplier = $suppliers[$i % $suppliers->count()];
            $order = $purchases->saveOrder(null, [
                'supplier_id' => $supplier->id,
                'date' => $date->toDateString(),
                'expected_date' => $date->copy()->addDays(7)->toDateString(),
                'delivery_address' => 'Main warehouse, Nairobi',
                'items' => $pick($stocked, mt_rand(2, 4))->map(fn ($p) => [
                    'product_id' => $p->id, 'quantity' => mt_rand(5, 25), 'unit_cost' => $p->cost_price,
                    'tax_rate' => $p->tax_percent, 'discount_percent' => 0,
                ])->all(),
            ]);

            // receive the first four LPOs (the last one only partially), leave the rest pending
            if ($i < 4) {
                $partial = $i === 3;
                $purchase = $purchases->create([
                    'supplier_id' => $supplier->id,
                    'purchase_order_id' => $order->id,
                    'supplier_invoice_no' => 'SI-'.mt_rand(10000, 99999),
                    'date' => $date->copy()->addDays(5)->toDateString(),
                    'items' => $order->items->map(fn ($it) => [
                        'product_id' => $it->product_id, 'purchase_order_item_id' => $it->id,
                        'quantity' => $partial ? max(1, floor($it->quantity / 2)) : $it->quantity,
                        'unit_cost' => $it->unit_cost, 'tax_rate' => $it->tax_rate, 'discount_percent' => 0,
                    ])->all(),
                    'payment_method' => $i % 2 ? 'bank' : 'credit',
                    'payment_amount' => 0,
                ]);
                $this->backdate($purchase, $date->copy()->addDays(5));
            }
        }

        for ($i = 0; $i < 12; $i++) {
            $date = now()->subDays(mt_rand(3, 115));
            $supplier = $suppliers->random();
            $method = ['credit', 'bank', 'cash', 'credit', 'mobile_money'][$i % 5];
            $purchase = $purchases->create([
                'supplier_id' => $supplier->id,
                'supplier_invoice_no' => 'INV-'.mt_rand(1000, 9999),
                'date' => $date->toDateString(),
                'discount_type' => 'fixed',
                'discount_value' => $i % 4 === 0 ? 500 : 0,
                'items' => $pick($stocked, mt_rand(1, 4))->map(fn ($p) => [
                    'product_id' => $p->id, 'quantity' => mt_rand(5, 30), 'unit_cost' => round($p->cost_price * (mt_rand(95, 105) / 100), 2),
                    'tax_rate' => $p->tax_percent, 'discount_percent' => $i % 3 === 0 ? 2.5 : 0,
                ])->all(),
                'payment_method' => $method,
                'payment_amount' => 0,
            ]);
            if ($method !== 'credit') {
                $amount = $i % 2 ? $purchase->total : round($purchase->total * 0.5, 2);
                $payments->record(['party_type' => 'supplier', 'supplier_id' => $supplier->id, 'date' => $date->toDateString(),
                    'amount' => $amount, 'method' => $method, 'reference' => strtoupper(substr($method, 0, 3)).mt_rand(100000, 999999), 'source' => 'invoice'],
                    [$purchase->id => $amount]);
            }
            $this->backdate($purchase, $date);
        }

        /* ---------------- Sales invoices ---------------- */
        $saleList = collect();
        for ($i = 0; $i < 70; $i++) {
            $date = now()->subDays(mt_rand(0, 120))->startOfDay();
            if ($i < 6) {
                $date = now()->startOfDay(); // some sales today for the dashboard
            }
            $customer = $customers->random();
            $lines = $pick($products, mt_rand(1, 4))->filter(fn ($p) => ! $p->track_stock || $p->fresh()->stock_quantity >= 3)
                ->map(fn ($p) => [
                    'product_id' => $p->id,
                    'quantity' => $p->track_stock ? mt_rand(1, min(5, (int) $p->fresh()->stock_quantity - 1)) : mt_rand(1, 6),
                    'unit_price' => $p->selling_price,
                    'discount_percent' => mt_rand(0, 5) === 0 ? 5 : 0,
                    'tax_rate' => $p->tax_percent,
                ])->filter(fn ($l) => $l['quantity'] > 0)->values()->all();
            if (! $lines) {
                continue;
            }

            // payment mix: ~40% paid in full, ~25% partial, ~35% on credit
            $roll = mt_rand(1, 100);
            $method = $roll <= 65 ? $methods[array_rand($methods)] : 'credit';

            $sale = $sales->create([
                'customer_id' => $customer->id,
                'date' => $date->toDateString(),
                'customer_reference' => mt_rand(0, 3) === 0 ? 'PO-'.mt_rand(1000, 9999) : null,
                'discount_type' => $i % 9 === 0 ? 'percentage' : 'fixed',
                'discount_value' => $i % 9 === 0 ? 3 : ($i % 7 === 0 ? 200 : 0),
                'items' => $lines,
                'payment_method' => 'credit',
                'payment_amount' => 0,
                'notes' => null,
            ]);

            if ($method !== 'credit') {
                $amount = $roll <= 40 ? $sale->total : round($sale->total * (mt_rand(30, 70) / 100), 2);
                $payments->record(['party_type' => 'customer', 'customer_id' => $customer->id, 'date' => $date->toDateString(),
                    'amount' => $amount, 'method' => $method,
                    'reference' => $method === 'mobile_money' ? 'S'.strtoupper(substr(md5((string) mt_rand()), 0, 9)) : null,
                    'notes' => 'Payment on invoice '.$sale->invoice_no, 'source' => 'invoice'],
                    [$sale->id => $amount]);
            }
            $this->backdate($sale, $date);
            $saleList->push($sale->fresh());
        }

        /* ---------------- Customer receipts against older invoices (FIFO) ---------------- */
        foreach ($customers->take(6) as $customer) {
            $due = Sale::where('customer_id', $customer->id)->where('due_amount', '>', 0)->orderBy('date')->get();
            if ($due->isEmpty()) {
                continue;
            }
            $amount = round($due->sum('due_amount') * 0.6, -2);
            $remaining = $amount;
            $alloc = [];
            foreach ($due as $s) {
                if ($remaining <= 0) {
                    break;
                }
                $alloc[$s->id] = min($s->due_amount, $remaining);
                $remaining = round($remaining - $alloc[$s->id], 2);
            }
            if ($amount > 0) {
                $payments->record(['party_type' => 'customer', 'customer_id' => $customer->id, 'date' => now()->subDays(mt_rand(1, 10))->toDateString(),
                    'amount' => $amount, 'method' => 'bank', 'reference' => 'EFT'.mt_rand(100000, 999999), 'notes' => 'Account settlement'], $alloc);
            }
        }

        /* ---------------- Supplier payments ---------------- */
        foreach ($suppliers->take(3) as $supplier) {
            $due = Purchase::where('supplier_id', $supplier->id)->where('due_amount', '>', 0)->orderBy('date')->get();
            if ($due->isEmpty()) {
                continue;
            }
            $first = $due->first();
            $amount = round($first->due_amount, 2);
            $payments->record(['party_type' => 'supplier', 'supplier_id' => $supplier->id, 'date' => now()->subDays(mt_rand(1, 15))->toDateString(),
                'amount' => $amount, 'method' => 'bank', 'reference' => 'RTGS'.mt_rand(10000, 99999), 'notes' => 'Supplier settlement'], [$first->id => $amount]);
        }

        /* ---------------- Sales returns ---------------- */
        foreach ($saleList->filter(fn ($s) => $s->date->lt(now()->subDays(10)))->take(4) as $k => $sale) {
            $item = $sale->items()->first();
            $qty = max(1, floor($item->quantity / 2));
            $return = $sales->createReturn($sale, [
                'date' => $sale->date->copy()->addDays(3)->toDateString(),
                'reason' => ['Damaged on delivery', 'Wrong item supplied', 'Customer changed order', 'Excess quantity'][$k % 4],
                'items' => [$item->id => $qty],
                'refund_amount' => 0,
            ]);
            $this->backdate($return, $sale->date->copy()->addDays(3));
        }

        /* ---------------- Purchase returns ---------------- */
        foreach (Purchase::orderBy('date')->take(2)->get() as $purchase) {
            $item = $purchase->items()->first();
            $return = $purchases->createReturn($purchase, [
                'date' => $purchase->date->copy()->addDays(4)->toDateString(),
                'reason' => 'Defective units',
                'items' => [$item->id => 1],
                'refund_amount' => 0,
            ]);
            $this->backdate($return, $purchase->date->copy()->addDays(4));
        }

        /* ---------------- Delivery notes (one per invoice, created automatically) ---------------- */
        foreach ($saleList->take(8) as $k => $sale) {
            $sale->shippingNote()->first()?->update([
                'vehicle_no' => 'KD'.chr(65 + $k).' '.mt_rand(100, 999).chr(65 + ($k * 3) % 26),
                'driver_name' => ['Moses Ouma', 'Ibrahim Ali', 'Stephen Kiptoo'][$k % 3],
                'status' => ['delivered', 'delivered', 'shipped', 'packed'][$k % 4],
            ]);
        }

        /* ---------------- Expenses ---------------- */
        $expenseCats = ExpenseCategory::pluck('id', 'name');
        $recurring = [['Rent', 85000, 'bank', 'Cape House Properties'], ['Salaries & Wages', 240000, 'bank', 'Staff payroll'],
            ['Utilities (Power & Water)', 14500, 'mobile_money', 'KPLC / Nairobi Water'], ['Internet & Telephone', 8999, 'mobile_money', 'Safaricom']];
        for ($m = 3; $m >= 0; $m--) {
            foreach ($recurring as [$cat, $amount, $method, $payee]) {
                $date = now()->subMonths($m)->startOfMonth()->addDays(mt_rand(0, 4));
                if ($date->isFuture()) {
                    continue;
                }
                Expense::create(['reference_no' => ReferenceService::next('expense'), 'date' => $date->toDateString(),
                    'expense_category_id' => $expenseCats[$cat], 'amount' => $amount + ($cat === 'Utilities (Power & Water)' ? mt_rand(-2000, 2000) : 0),
                    'payment_method' => $method, 'payee' => $payee, 'notes' => $cat.' - '.$date->format('F Y')]);
            }
        }
        $adhoc = [['Transport & Fuel', 2500, 9000, 'Total Energies'], ['Office Supplies', 800, 4500, 'Office Mart'], ['Marketing', 5000, 25000, 'Facebook Ads'],
            ['Repairs & Maintenance', 1500, 12000, 'Fundi Services'], ['Bank Charges', 150, 1200, 'KCB Bank'], ['Miscellaneous', 300, 3000, 'Petty cash']];
        for ($i = 0; $i < 30; $i++) {
            [$cat, $min, $max, $payee] = $adhoc[$i % count($adhoc)];
            Expense::create(['reference_no' => ReferenceService::next('expense'), 'date' => now()->subDays(mt_rand(0, 115))->toDateString(),
                'expense_category_id' => $expenseCats[$cat], 'amount' => round(mt_rand($min, $max), -1),
                'payment_method' => ['cash', 'mobile_money', 'bank'][$i % 3], 'payee' => $payee]);
        }

        /* ---------------- Stock adjustments ---------------- */
        foreach ([['decrease', 'Damaged / expired stock'], ['decrease', 'Stock count variance'], ['increase', 'Stock count surplus']] as $k => [$type, $reason]) {
            $product = $stocked[$k * 3 + 2]->fresh();
            $qty = 2;
            $date = now()->subDays(20 - $k * 5);
            $adj = StockAdjustment::create(['reference_no' => ReferenceService::next('adjustment'), 'date' => $date->toDateString(),
                'type' => $type, 'reason' => $reason, 'total_amount' => round($qty * $product->cost_price, 2)]);
            $adj->items()->create(['product_id' => $product->id, 'quantity' => $qty, 'unit_cost' => $product->cost_price, 'subtotal' => round($qty * $product->cost_price, 2)]);
            $stock->move($product, $type === 'increase' ? $qty : -$qty, 'adjustment', $adj, $adj->reference_no, $date->toDateString(), $product->cost_price, $reason);
        }

        Auth::forgetUser();
        $this->command?->info('Demo data seeded: '.Product::count().' products, '.Sale::count().' invoices, '.Purchase::count().' purchases.');
    }

    /** Align created_at with the document date so activity and "recent" lists look natural. */
    protected function backdate($model, Carbon $date): void
    {
        $model->timestamps = false;
        $model->created_at = $date->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59));
        $model->updated_at = $model->created_at;
        $model->saveQuietly();
    }
}
