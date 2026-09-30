@extends('layouts.app')

@php
    $editing = $sale->exists;
    $oldItems = old('items');
@endphp

@section('title', $editing ? 'Edit Invoice '.$sale->invoice_no : 'Create Sales Invoice')

@section('header_actions')
    <a href="{{ $editing ? route('sales.show', $sale) : route('sales.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    <form id="doc-form" action="{{ $editing ? route('sales.update', $sale) : route('sales.store') }}" method="POST" class="no-lock" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required" for="customer_id">Customer</label>
                            <div class="input-group">
                                <select name="customer_id" id="customer_id" class="form-control" required data-placeholder="Search customer by name, code or phone">
                                    @if ($customer)
                                        <option value="{{ $customer->id }}" selected>{{ $customer->display_name }}</option>
                                    @endif
                                </select>
                            </div>
                            <small class="text-muted" id="customer_info">
                                @if ($customer)
                                    Balance: {{ money($customer->currentBalance()) }}
                                    @if ($customer->credit_limit) &middot; Credit limit: {{ money($customer->credit_limit) }}@endif
                                @endif
                            </small>
                            @can('customers.create')
                                <div><a href="#" class="small btn-modal" data-href="{{ route('customers.create') }}"><i class="fas fa-plus-circle"></i> New customer</a></div>
                            @endcan
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="required" for="date">Invoice date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', optional($sale->date)->toDateString() ?? now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="payment_terms">Pay term (days)</label>
                            <input type="number" min="0" max="365" name="payment_terms" id="payment_terms" class="form-control" value="{{ old('payment_terms', $sale->payment_terms ?? $customer?->payment_terms ?? settings('default_payment_terms')) }}">
                            <small class="text-muted">Sets the due date</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="due_date">Due date</label>
                            <input type="date" name="due_date" id="due_date" class="form-control" value="{{ old('due_date', optional($sale->due_date)->toDateString()) }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="customer_reference">Customer PO / LPO No.</label>
                            <input type="text" name="customer_reference" id="customer_reference" class="form-control" value="{{ old('customer_reference', $sale->customer_reference) }}" maxlength="190">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Invoice No.</label>
                            <input type="text" class="form-control" value="{{ $editing ? $sale->invoice_no : 'Auto-generated' }}" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-teal card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-boxes mr-1 text-teal"></i> Items <span class="badge badge-primary ml-1" id="item_count">0</span></h3>
            </div>
            <div class="card-body">
                <div class="row justify-content-center mb-3">
                    <div class="col-md-8 product-search-wrap">
                        <select id="product_search" class="form-control"></select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered items-table" id="items_table">
                        <thead>
                        <tr>
                            <th class="text-center" style="width:40px">#</th>
                            <th>Product</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Disc %</th>
                            <th>Tax</th>
                            <th class="text-right">Line Total</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('sales.partials.shipping')

        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label for="notes">Notes (shown on invoice)</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control" maxlength="2000">{{ old('notes', $sale->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                @unless ($editing)
                    @include('partials.payment-rows', ['title' => 'Add payment', 'hint' => 'Leave the amount at 0 to invoice on credit. Partial and split payments (e.g. cash + mobile money) are allowed.'])
                @else
                    <div class="alert alert-light border">
                        <i class="fas fa-info-circle text-primary mr-1"></i>
                        Payments already recorded ({{ money($sale->paid_amount) }}) stay allocated to this invoice. Use <strong>Add Payment</strong> from the invoice to record more.
                    </div>
                @endunless
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <table class="table totals-table mb-0">
                            <tr>
                                <td>Subtotal (excl. tax)</td>
                                <td class="text-right" id="subtotal_text">0.00</td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="mr-2">Discount</span>
                                        <select name="discount_type" id="discount_type" class="form-control form-control-sm custom-select mr-1" style="width:95px">
                                            <option value="fixed" @selected(old('discount_type', $sale->discount_type) === 'fixed')>Fixed</option>
                                            <option value="percentage" @selected(old('discount_type', $sale->discount_type) === 'percentage')>%</option>
                                        </select>
                                        <input type="number" step="any" min="0" name="discount_value" id="discount_value" class="form-control form-control-sm" style="width:100px" value="{{ old('discount_value', (float) $sale->discount_value) }}">
                                    </div>
                                </td>
                                <td class="text-right" id="discount_text">0.00</td>
                            </tr>
                            <tr>
                                <td>{{ settings('tax_label', 'Tax') }} <small class="text-muted">(on discounted amount)</small></td>
                                <td class="text-right" id="tax_text">0.00</td>
                            </tr>
                            <tr class="charges-row">
                                <td>Shipping charges</td>
                                <td class="text-right" id="shipping_text">0.00</td>
                            </tr>
                            <tr class="charges-row">
                                <td>Additional expenses</td>
                                <td class="text-right" id="charges_text">0.00</td>
                            </tr>
                            <tr class="grand">
                                <td>Invoice Total</td>
                                <td class="text-right" id="total_text">0.00</td>
                            </tr>
                        </table>
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" name="submit_action" value="save" class="btn btn-primary"><i class="fas fa-save"></i> {{ $editing ? 'Update Invoice' : 'Save Invoice' }}</button>
                        <button type="submit" name="submit_action" value="print" class="btn btn-teal"><i class="fas fa-print"></i> Save &amp; Print</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script src="{{ asset('js/doc-form.js') }}?v={{ filemtime(public_path('js/doc-form.js')) }}"></script>
<script>
    $(function () {
        @php
            // Re-populate rows after a failed validation (old input), otherwise existing invoice lines.
            $initialItems = $items;
            if (is_array($oldItems)) {
                $products = \App\Models\Product::withTrashed()->with('unit')->whereIn('id', collect($oldItems)->pluck('product_id'))->get()->keyBy('id');
                $initialItems = collect($oldItems)->map(function ($row) use ($products) {
                    $p = $products[$row['product_id'] ?? 0] ?? null;
                    return $p ? [
                        'id' => $p->id, 'text' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit->short_name ?? '',
                        'stock' => (float) $p->stock_quantity, 'track_stock' => $p->track_stock,
                        'price' => (float) ($row['unit_price'] ?? 0), 'quantity' => (float) ($row['quantity'] ?? 1),
                        'discount_percent' => (float) ($row['discount_percent'] ?? 0), 'tax_rate_line' => (float) ($row['tax_rate'] ?? 0),
                        'description' => $row['description'] ?? null,
                    ] : null;
                })->filter()->values()->all();
            }
        @endphp

        DocForm.init({
            priceName: 'unit_price',
            priceField: 'selling_price',
            checkStock: true,
            withDescription: true,
            searchUrl: '{{ route('products.search') }}',
            taxRates: @json($taxRates),
            items: @json($initialItems)
        });

        // Pay term drives the due date; the due date can still be overridden manually.
        function setDueDate() {
            var d = $('#date').val(), days = parseInt($('#payment_terms').val(), 10);
            if (!d || isNaN(days)) { return; }
            $('#due_date').val(moment(d).add(days, 'days').format('YYYY-MM-DD'));
        }

        APP.contactSelect('#customer_id', '{{ route('customers.search') }}').on('select2:select', function (e) {
            var c = e.params.data;
            var info = [];
            if (c.balance !== undefined) { info.push('Balance: ' + APP.formatMoney(c.balance)); }
            if (c.credit_limit) { info.push('Credit limit: ' + APP.formatMoney(c.credit_limit)); }
            $('#customer_info').html(info.join(' &middot; '));
            if (c.payment_terms !== undefined && c.payment_terms !== null && c.payment_terms !== '') {
                $('#payment_terms').val(parseInt(c.payment_terms, 10));
            }
            setDueDate();
        });

        $('#date, #payment_terms').on('change input', setDueDate);
        @unless ($editing)
            if (!$('#due_date').val()) { setDueDate(); }
        @endunless

        // New customer created from the modal: select it
        $(document).on('app:saved', function (e, res) {
            if (res && (res.contact || res.customer)) {
                var created = res.contact || res.customer;
                var opt = new Option(created.text, created.id, true, true);
                $('#customer_id').append(opt).trigger('change');
                if (created.payment_terms !== undefined && created.payment_terms !== null && created.payment_terms !== '') { $('#payment_terms').val(created.payment_terms); setDueDate(); }
            }
        });

        $('#doc-form').on('submit', function () {
            if ($('#items_table tbody tr.item-row').length) {
                $(this).find('[type=submit]').prop('disabled', true);
                // keep the clicked button's value
                var action = $(document.activeElement).val();
                if (action) { $('<input type="hidden" name="submit_action">').val(action).appendTo(this); }
            }
        });
    });
</script>
@endpush
