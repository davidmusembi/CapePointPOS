@extends('layouts.app')

@section('title', 'Business Settings')
@section('page_subtitle', 'Company profile, currency, tax and numbering')

@php
    $field = fn ($name, $default = null) => old($name, $setting->{$name} ?? $default);
    $prefixes = [
        'invoice_prefix' => 'Sales invoice', 'credit_note_prefix' => 'Credit note (sales return)', 'delivery_note_prefix' => 'Delivery note',
        'lpo_prefix' => 'Local purchase order', 'purchase_prefix' => 'Purchase / GRN', 'purchase_return_prefix' => 'Debit note (purchase return)',
        'customer_payment_prefix' => 'Customer receipt', 'supplier_payment_prefix' => 'Supplier payment', 'expense_prefix' => 'Expense',
        'adjustment_prefix' => 'Stock adjustment',
    ];
@endphp

@section('content')
    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card card-primary card-outline card-tabs">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#tab_business"><i class="fas fa-building mr-1"></i> Business</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab_currency"><i class="fas fa-coins mr-1"></i> Currency &amp; Tax</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab_prefixes"><i class="fas fa-hashtag mr-1"></i> Numbering</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#tab_sales"><i class="fas fa-file-invoice mr-1"></i> Invoice &amp; Stock</a></li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab_business">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-12 form-group">
                                        <label class="required">Business name</label>
                                        <input type="text" name="business_name" class="form-control" value="{{ $field('business_name') }}" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control" value="{{ $field('email') }}">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Phone</label>
                                        <input type="text" name="phone" class="form-control" value="{{ $field('phone') }}">
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label>Address</label>
                                        <input type="text" name="address" class="form-control" value="{{ $field('address') }}">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>City</label>
                                        <input type="text" name="city" class="form-control" value="{{ $field('city') }}">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Country</label>
                                        <input type="text" name="country" class="form-control" value="{{ $field('country') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label>Logo</label>
                                <div class="border rounded p-3 text-center mb-2" style="background:#fafbfd">
                                    @if ($setting->logo_url)
                                        <img src="{{ $setting->logo_url }}" alt="Logo" style="max-height:90px;max-width:100%">
                                    @else
                                        <div class="text-muted py-3"><i class="far fa-image fa-2x d-block mb-1"></i>No logo uploaded</div>
                                    @endif
                                </div>
                                <input type="file" name="logo" class="form-control-file" accept="image/*">
                                <small class="text-muted">PNG/JPG, max 1 MB. Shown on invoices and PDFs.</small>
                                @if ($setting->logo)
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="checkbox" class="custom-control-input" id="remove_logo" name="remove_logo" value="1">
                                        <label class="custom-control-label font-weight-normal" for="remove_logo">Remove current logo</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab_currency">
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label class="required">Currency code</label>
                                <input type="text" name="currency_code" class="form-control" value="{{ $field('currency_code') }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="required">Currency symbol</label>
                                <input type="text" name="currency_symbol" class="form-control" value="{{ $field('currency_symbol') }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="required">Symbol position</label>
                                <select name="currency_position" class="form-control custom-select">
                                    <option value="before" @selected($field('currency_position') === 'before')>Before amount ({{ $field('currency_symbol') }} 100)</option>
                                    <option value="after" @selected($field('currency_position') === 'after')>After amount (100 {{ $field('currency_symbol') }})</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="required">Decimal places</label>
                                <input type="number" min="0" max="2" name="decimal_places" class="form-control" value="{{ $field('decimal_places', 2) }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Thousand separator</label>
                                <select name="thousand_separator" class="form-control custom-select">
                                    @foreach ([',' => 'Comma (1,000)', '.' => 'Dot (1.000)', 'space' => 'Space (1 000)', '' => 'None (1000)'] as $k => $v)
                                        <option value="{{ $k }}" @selected(($field('thousand_separator') === ' ' ? 'space' : $field('thousand_separator')) === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="required">Decimal separator</label>
                                <select name="decimal_separator" class="form-control custom-select">
                                    <option value="." @selected($field('decimal_separator') === '.')>Dot (0.50)</option>
                                    <option value="," @selected($field('decimal_separator') === ',')>Comma (0,50)</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="required">Date format</label>
                                <select name="date_format" class="form-control custom-select">
                                    @foreach ($dateFormats as $k => $v)
                                        <option value="{{ $k }}" @selected($field('date_format') === $k)>{{ $v }} ({{ now()->format($k) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="required">Financial year starts</label>
                                <select name="financial_year_start_month" class="form-control custom-select">
                                    @foreach (range(1, 12) as $m)
                                        <option value="{{ $m }}" @selected((int) $field('financial_year_start_month', 1) === $m)>{{ \Illuminate\Support\Carbon::create(null, $m, 1)->format('F') }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Used by "Current / Last financial year" filters.</small>
                            </div>
                        </div>
                        <div class="form-section-title">Tax</div>
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label class="required">Tax label</label>
                                <input type="text" name="tax_label" class="form-control" value="{{ $field('tax_label', 'VAT') }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Tax number / PIN</label>
                                <input type="text" name="tax_number" class="form-control" value="{{ $field('tax_number') }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Default tax for new products</label>
                                <select name="default_tax_rate_id" class="form-control custom-select">
                                    <option value="">None</option>
                                    @foreach ($taxRates as $t)
                                        <option value="{{ $t->id }}" @selected($field('default_tax_rate_id') == $t->id)>{{ $t->label }}</option>
                                    @endforeach
                                </select>
                                <small><a href="{{ route('tax-rates.index') }}">Manage tax rates</a></small>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab_prefixes">
                        <p class="text-muted">Document numbers are generated as <code>PREFIX + YEAR - sequence</code>, e.g. <code>{{ $field('invoice_prefix', 'INV') }}{{ now()->year }}-00001</code>. Sequences restart every year.</p>
                        <div class="row">
                            @foreach ($prefixes as $name => $label)
                                <div class="col-md-3 form-group">
                                    <label class="required">{{ $label }}</label>
                                    <input type="text" name="{{ $name }}" class="form-control text-uppercase" value="{{ $field($name) }}" required maxlength="20">
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab_sales">
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label class="required">Default payment terms (days)</label>
                                <input type="number" min="0" max="365" name="default_payment_terms" class="form-control" value="{{ $field('default_payment_terms', 30) }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="required">Default alert quantity</label>
                                <input type="number" step="any" min="0" name="default_alert_quantity" class="form-control" value="{{ $field('default_alert_quantity', 5) }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>&nbsp;</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="allow_negative_stock" name="allow_negative_stock" value="1" @checked($field('allow_negative_stock'))>
                                    <label class="custom-control-label" for="allow_negative_stock">Allow selling more than available stock (negative stock)</label>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="required">Payment methods offered</label>
                                @php $enabledMethods = old('enabled_payment_methods', $setting->enabled_payment_methods ?: array_keys(config('pos.payment_methods'))); @endphp
                                @foreach (config('pos.payment_methods') as $key => $label)
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="pm_{{ $key }}" name="enabled_payment_methods[]" value="{{ $key }}" @checked(in_array($key, (array) $enabledMethods, true))>
                                        <label class="custom-control-label font-weight-normal" for="pm_{{ $key }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                                <small class="text-muted">Shown on invoices, purchases, payments and expenses.</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Stock adjustment reasons</label>
                                <textarea name="stock_adjustment_reasons" rows="5" class="form-control" placeholder="{{ implode("\n", config('pos.stock_adjustment_reasons')) }}">{{ $field('stock_adjustment_reasons') }}</textarea>
                                <small class="text-muted">One per line. Leave blank for the defaults.</small>
                            </div>
                            <div class="col-md-12 form-group">
                                <label>Invoice terms &amp; conditions / payment instructions</label>
                                <textarea name="invoice_terms" rows="5" class="form-control">{{ $field('invoice_terms') }}</textarea>
                            </div>
                            <div class="col-md-12 form-group">
                                <label>Document footer text</label>
                                <input type="text" name="invoice_footer" class="form-control" value="{{ $field('invoice_footer') }}" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save settings</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Keep the active tab after validation errors / reload
    $(function () {
        var key = 'settings_tab', saved = null;
        try { saved = localStorage.getItem(key); } catch (e) {}
        if (saved) { $('a[href="' + saved + '"]').tab('show'); }
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) { try { localStorage.setItem(key, $(e.target).attr('href')); } catch (err) {} });
    });
</script>
@endpush
