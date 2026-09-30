<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $vat = TaxRate::firstOrCreate(['name' => 'VAT'], ['rate' => 16]);
        TaxRate::firstOrCreate(['name' => 'Zero Rated'], ['rate' => 0]);
        TaxRate::firstOrCreate(['name' => 'Excise'], ['rate' => 8]);

        if (! Setting::exists()) {
            Setting::create([
                'business_name' => 'CapePoint Trading Ltd',
                'email' => 'accounts@capepoint.co.ke',
                'phone' => '+254 700 123 456',
                'address' => 'Kenyatta Avenue, Cape House, 3rd Floor',
                'city' => 'Nairobi',
                'country' => 'Kenya',
                'tax_number' => 'P051234567X',
                'tax_label' => 'VAT',
                'default_tax_rate_id' => $vat->id,
                'currency_code' => 'KES',
                'currency_symbol' => 'KSh',
                'currency_position' => 'before',
                'decimal_places' => 2,
                'date_format' => 'd/m/Y',
                'default_payment_terms' => 30,
                'default_alert_quantity' => 10,
                'allow_negative_stock' => false,
                'invoice_terms' => "Payment is due within 30 days of the invoice date.\nGoods once sold are not returnable after 7 days.\nBank: KCB Bank, A/C 1234567890, Branch: Moi Avenue.\nM-Pesa Paybill: 522522, Account: Invoice No.",
                'invoice_footer' => 'Thank you for your business!',
            ]);
        }
        Setting::flush();

        foreach ([
            ['Pieces', 'Pcs', false], ['Box', 'Box', false], ['Carton', 'Ctn', false], ['Kilogram', 'Kg', true],
            ['Litre', 'Ltr', true], ['Metre', 'M', true], ['Pack', 'Pack', false], ['Service', 'Svc', false],
        ] as [$name, $short, $decimal]) {
            Unit::firstOrCreate(['name' => $name], ['short_name' => $short, 'allow_decimal' => $decimal]);
        }

        foreach ([
            ['Electronics', 'ELEC'], ['Stationery', 'STAT'], ['Office Furniture', 'FURN'], ['Networking', 'NET'],
            ['Cleaning Supplies', 'CLN'], ['Beverages', 'BEV'], ['Services', 'SRV'],
        ] as [$name, $code]) {
            Category::firstOrCreate(['name' => $name], ['code' => $code]);
        }

        foreach ([
            'Rent', 'Salaries & Wages', 'Utilities (Power & Water)', 'Internet & Telephone', 'Transport & Fuel',
            'Office Supplies', 'Marketing', 'Repairs & Maintenance', 'Bank Charges', 'Miscellaneous',
        ] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }

        // Demo accounts (sign in with the username; password "password")
        $users = [
            ['Administrator', 'admin', 'admin@capepoint.test', 'Admin'],
            ['Mary Manager', 'manager', 'manager@capepoint.test', 'Manager'],
            ['Alex Accountant', 'accountant', 'accountant@capepoint.test', 'Accountant'],
            ['Victor Viewer', 'viewer', 'viewer@capepoint.test', 'Viewer'],
        ];
        foreach ($users as [$name, $username, $email, $role]) {
            $user = User::firstOrCreate(['username' => $username], [
                'email' => $email,
                'name' => $name,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);
        }
    }
}
