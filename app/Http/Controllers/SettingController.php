<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public const DATE_FORMATS = ['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD', 'd-m-Y' => 'DD-MM-YYYY', 'd M Y' => 'DD Mon YYYY'];

    public function edit()
    {
        return view('settings.edit', [
            'setting' => Setting::first() ?? new Setting(Setting::current()->getAttributes()),
            'taxRates' => TaxRate::orderBy('name')->get(),
            'dateFormats' => self::DATE_FORMATS,
        ]);
    }

    public function update(Request $request)
    {
        $prefix = ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/]+$/'];
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'tax_label' => ['required', 'string', 'max:30'],
            'default_tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'currency_code' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_position' => ['required', Rule::in(['before', 'after'])],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:2'],
            'thousand_separator' => ['nullable', 'string', 'max:3'],
            'decimal_separator' => ['required', 'string', 'max:3'],
            'date_format' => ['required', Rule::in(array_keys(self::DATE_FORMATS))],
            'financial_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'invoice_prefix' => $prefix,
            'credit_note_prefix' => $prefix,
            'delivery_note_prefix' => $prefix,
            'lpo_prefix' => $prefix,
            'purchase_prefix' => $prefix,
            'purchase_return_prefix' => $prefix,
            'customer_payment_prefix' => $prefix,
            'supplier_payment_prefix' => $prefix,
            'expense_prefix' => $prefix,
            'adjustment_prefix' => $prefix,
            'default_payment_terms' => ['required', 'integer', 'min:0', 'max:365'],
            'default_alert_quantity' => ['required', 'numeric', 'min:0'],
            'invoice_terms' => ['nullable', 'string', 'max:3000'],
            'enabled_payment_methods' => ['required', 'array', 'min:1'],
            'enabled_payment_methods.*' => [Rule::in(array_keys(config('pos.payment_methods')))],
            'stock_adjustment_reasons' => ['nullable', 'string', 'max:2000'],
            'invoice_footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        $setting = Setting::first() ?? new Setting;
        $data['allow_negative_stock'] = $request->boolean('allow_negative_stock');
        $data['thousand_separator'] = $request->input('thousand_separator') === 'space' ? ' ' : ($data['thousand_separator'] ?? '');

        if ($request->hasFile('logo')) {
            if ($setting->logo) {
                Storage::disk('public')->delete($setting->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        } elseif ($request->boolean('remove_logo') && $setting->logo) {
            Storage::disk('public')->delete($setting->logo);
            $data['logo'] = null;
        } else {
            unset($data['logo']);
        }

        $setting->fill($data)->save();
        Setting::flush();

        return redirect()->route('settings.edit')->with('success', 'Business settings updated');
    }
}
