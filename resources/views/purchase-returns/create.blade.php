@extends('layouts.app')

@section('title', 'New Purchase Return')
@section('page_subtitle', $purchase ? $purchase->purchase_no.' · '.($purchase->supplier->display_name ?? '') : 'Select the purchase to return goods from')

@section('header_actions')
    <a href="{{ $purchase ? route('purchases.show', $purchase) : route('purchase-returns.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    @unless ($purchase)
        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group mb-0">
                            <label for="purchase_picker">Purchase</label>
                            <select id="purchase_picker" class="form-control"></select>
                            <small class="text-muted">Search by purchase no., supplier invoice no. or supplier name.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <form action="{{ route('purchase-returns.store') }}" method="POST" id="return_form">
            @csrf
            <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">

            <div class="card card-primary card-outline">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3"><dl class="doc-meta"><dt>Purchase</dt><dd><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_no }}</a></dd></dl></div>
                        <div class="col-md-3"><dl class="doc-meta"><dt>Supplier</dt><dd>{{ $purchase->supplier->display_name ?? '-' }}</dd></dl></div>
                        <div class="col-md-2"><dl class="doc-meta"><dt>Purchase date</dt><dd>{{ format_date($purchase->date) }}</dd></dl></div>
                        <div class="col-md-2"><dl class="doc-meta"><dt>Paid / Due</dt><dd>{{ money($purchase->paid_amount) }} / {{ money($purchase->due_amount) }}</dd></dl></div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="required" for="date">Return date</label>
                                <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered items-table" id="return_items">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Purchased</th>
                                <th class="text-right">Returned</th>
                                <th class="text-right">Returnable</th>
                                <th class="text-right">In stock</th>
                                <th class="text-right">Unit cost (net)</th>
                                <th style="width:130px" class="text-right">Return qty</th>
                                <th class="text-right">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($purchase->items as $item)
                                @php
                                    $unitNet = $item->quantity > 0 ? $item->net_amount / $item->quantity : 0;
                                    $unitTax = $item->quantity > 0 ? $item->tax_amount / $item->quantity : 0;
                                    $max = min($item->returnable_quantity, $item->product && $item->product->track_stock ? max(0, $item->product->stock_quantity) : $item->returnable_quantity);
                                @endphp
                                <tr data-net="{{ $unitNet }}" data-tax="{{ $unitTax }}">
                                    <td><span class="product-name">{{ $item->product->name ?? '-' }}</span><div class="product-meta">{{ $item->product->sku ?? '' }}</div></td>
                                    <td class="text-right">{{ qty_format($item->quantity) }}</td>
                                    <td class="text-right">{{ qty_format($item->returned_quantity) }}</td>
                                    <td class="text-right">{{ qty_format($item->returnable_quantity) }}</td>
                                    <td class="text-right">{{ $item->product && $item->product->track_stock ? qty_format($item->product->stock_quantity) : 'N/A' }}</td>
                                    <td class="text-right">{{ money($unitNet) }}</td>
                                    <td>
                                        <input type="number" step="any" min="0" max="{{ $max }}" name="items[{{ $item->id }}]" class="form-control text-right return-qty"
                                               value="{{ old('items.'.$item->id, 0) }}" @disabled($max <= 0)>
                                    </td>
                                    <td class="text-right line-amount">0.00</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('items')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <input type="text" name="reason" id="reason" class="form-control" value="{{ old('reason') }}" placeholder="e.g. Damaged / defective units" maxlength="255">
                            </div>
                            <div class="form-group mb-0">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <table class="table totals-table">
                                <tr><td>Subtotal</td><td class="text-right" id="r_subtotal">0.00</td></tr>
                                <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right" id="r_tax">0.00</td></tr>
                                <tr><td>Share of purchase discount</td><td class="text-right text-danger" id="r_discount">0.00</td></tr>
                                <tr class="grand"><td>Debit note total</td><td class="text-right" id="r_total">0.00</td></tr>
                            </table>
                            <div class="payment-panel">
                                <div class="form-section-title">Refund received from supplier (optional)</div>
                                <div class="row">
                                    <div class="col-6">
                                        <label for="refund_amount">Amount</label>
                                        <input type="number" step="any" min="0" name="refund_amount" id="refund_amount" class="form-control text-right" value="{{ old('refund_amount', 0) }}">
                                        <small class="text-muted">Max refundable: <span id="r_max_refund">0.00</span></small>
                                    </div>
                                    <div class="col-6">
                                        <label for="refund_method">Method</label>
                                        <select name="refund_method" id="refund_method" class="form-control custom-select">
                                            @foreach (payment_methods() as $key => $label)
                                                <option value="{{ $key }}" @selected(old('refund_method') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">Without a refund, the debit note reduces the amount owed to the supplier.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-right mb-4">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Purchase Return</button>
            </div>
        </form>
    @endunless
@endsection

@push('scripts')
<script>
    $(function () {
        @unless ($purchase)
            $('#purchase_picker').select2({
                theme: 'bootstrap4', width: '100%', placeholder: 'Search purchase...',
                ajax: { url: '{{ route('purchase-returns.index') }}', dataType: 'json', delay: 250,
                    data: function (p) { return { lookup: 1, q: p.term }; },
                    processResults: function (d) { return { results: d.results }; } }
            }).on('select2:select', function (e) {
                window.location = '{{ route('purchase-returns.create') }}?purchase_id=' + e.params.data.id;
            });
        @else
            var factor = {{ $factor }};
            var paid = {{ (float) $purchase->paid_amount }}, net = {{ (float) ($purchase->total - $purchase->returned_amount) }};
            function recalc() {
                var sub = 0, tax = 0;
                $('#return_items tbody tr').each(function () {
                    var $r = $(this), $q = $r.find('.return-qty');
                    var q = Math.max(0, parseFloat($q.val()) || 0), max = parseFloat($q.attr('max'));
                    $r.toggleClass('table-danger', q > max);
                    var n = APP.round(parseFloat($r.data('net')) * q), t = APP.round(parseFloat($r.data('tax')) * q);
                    $r.find('.line-amount').text(APP.formatNumber(n + t));
                    sub += n; tax += t;
                });
                sub = APP.round(sub); tax = APP.round(tax);
                var disc = APP.round((sub + tax) * factor), total = APP.round(sub + tax - disc);
                $('#r_subtotal').text(APP.formatMoney(sub));
                $('#r_tax').text(APP.formatMoney(tax));
                $('#r_discount').text('- ' + APP.formatMoney(disc));
                $('#r_total').text(APP.formatMoney(total));
                $('#r_max_refund').text(APP.formatMoney(Math.max(0, paid - (net - total))));
            }
            $(document).on('input', '.return-qty', recalc);
            recalc();
        @endunless
    });
</script>
@endpush
