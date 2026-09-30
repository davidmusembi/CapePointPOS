@extends('layouts.app')

@section('title', 'Sales Return')
@section('page_subtitle', 'Invoice '.$sale->invoice_no)

@section('header_actions')
    <a href="{{ route('sales.show', $sale) }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back to invoice</a>
@endsection

@section('content')
    <form action="{{ route('sale-returns.store') }}" method="POST" id="return_form">
        @csrf
        <input type="hidden" name="sale_id" value="{{ $sale->id }}">

        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <dl class="doc-meta mb-0">
                            <dt>Invoice</dt><dd><a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_no }}</a> <small class="text-muted">{{ format_date($sale->date) }}</small></dd>
                            <dt>Customer</dt><dd>{{ $sale->customer->display_name }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-3">
                        <dl class="doc-meta mb-0">
                            <dt>Invoice total</dt><dd>{{ money($sale->total) }}</dd>
                            <dt>Paid / Due</dt><dd>{{ money($sale->paid_amount) }} / <span class="{{ $sale->due_amount > 0 ? 'text-danger' : '' }}">{{ money($sale->due_amount) }}</span></dd>
                        </dl>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="required" for="date">Return date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="reason">Reason</label>
                            <input type="text" name="reason" id="reason" class="form-control" value="{{ old('reason') }}" list="reasons" maxlength="190">
                            <datalist id="reasons">
                                <option value="Damaged on delivery"><option value="Wrong item supplied"><option value="Customer changed order"><option value="Excess quantity"><option value="Faulty / defective">
                            </datalist>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-teal card-outline">
            <div class="card-header"><h3 class="card-title">Items to return</h3></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered items-table" id="return_items">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-right">Sold</th>
                            <th class="text-right">Returned</th>
                            <th class="text-right">Returnable</th>
                            <th class="text-right">Unit Price (net)</th>
                            <th class="text-right">Tax / unit</th>
                            <th class="text-right" style="width:130px">Return Qty</th>
                            <th class="text-right">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($sale->items as $i => $item)
                            @php
                                $unitNet = $item->quantity > 0 ? $item->net_amount / $item->quantity : 0;
                                $unitTax = $item->quantity > 0 ? $item->tax_amount / $item->quantity : 0;
                            @endphp
                            <tr class="return-row" data-net="{{ $unitNet }}" data-tax="{{ $unitTax }}">
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <div class="product-name">{{ $item->product->name ?? '-' }}</div>
                                    <div class="product-meta">{{ $item->product->sku ?? '' }}</div>
                                </td>
                                <td class="text-right">{{ qty_format($item->quantity) }}</td>
                                <td class="text-right">{{ qty_format($item->returned_quantity) }}</td>
                                <td class="text-right font-weight-600">{{ qty_format($item->returnable_quantity) }}</td>
                                <td class="text-right">{{ money($unitNet) }}</td>
                                <td class="text-right">{{ money($unitTax) }}</td>
                                <td>
                                    <input type="number" step="any" min="0" max="{{ $item->returnable_quantity }}" name="items[{{ $item->id }}]"
                                           class="form-control text-right return-qty" value="{{ old('items.'.$item->id, 0) }}" @disabled($item->returnable_quantity <= 0)>
                                </td>
                                <td class="text-right line-amount font-weight-600">0.00</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="payment-panel mb-3">
                    <div class="form-section-title"><i class="fas fa-hand-holding-usd mr-1"></i> Refund (optional)</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="refund_amount">Refund amount</label>
                                <input type="number" step="any" min="0" name="refund_amount" id="refund_amount" class="form-control" value="{{ old('refund_amount', 0) }}">
                                <small class="text-muted">Max refundable: <strong id="max_refund">0.00</strong></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="refund_method">Refund method</label>
                                <select name="refund_method" id="refund_method" class="form-control custom-select">
                                    @foreach (payment_methods() as $key => $label)
                                        <option value="{{ $key }}" @selected(old('refund_method') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted">The credit note first reduces the invoice balance. Only money the customer has already paid in excess of the new invoice balance can be refunded; anything not refunded stays as credit on the customer's account.</small>
                </div>
                <div class="card">
                    <div class="card-body">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <table class="table totals-table mb-0">
                            <tr><td>Subtotal</td><td class="text-right" id="r_subtotal">0.00</td></tr>
                            <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right" id="r_tax">0.00</td></tr>
                            @if ($factor > 0)
                                <tr><td>Less invoice discount share</td><td class="text-right" id="r_discount">0.00</td></tr>
                            @endif
                            <tr class="grand"><td>Credit Note Total</td><td class="text-right" id="r_total">0.00</td></tr>
                        </table>
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Credit Note</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $(function () {
        var factor = {{ $factor }};
        var paid = {{ (float) $sale->paid_amount }};
        var openNet = {{ round($sale->total - $sale->returned_amount, 2) }};

        function recalc() {
            var sub = 0, tax = 0;
            $('#return_items tr.return-row').each(function () {
                var $r = $(this), $q = $r.find('.return-qty');
                var max = parseFloat($q.attr('max')) || 0;
                var q = Math.max(0, parseFloat($q.val()) || 0);
                $q.toggleClass('is-invalid', q > max + 0.0005);
                var net = APP.round(q * parseFloat($r.data('net')));
                var t = APP.round(q * parseFloat($r.data('tax')));
                $r.find('.line-amount').text(APP.formatNumber(net + t));
                sub += net; tax += t;
            });
            sub = APP.round(sub); tax = APP.round(tax);
            var disc = APP.round((sub + tax) * factor);
            var total = APP.round(sub + tax - disc);
            $('#r_subtotal').text(APP.formatMoney(sub));
            $('#r_tax').text(APP.formatMoney(tax));
            $('#r_discount').text('- ' + APP.formatMoney(disc));
            $('#r_total').text(APP.formatMoney(total));
            var maxRefund = Math.max(0, APP.round(paid - (openNet - total)));
            $('#max_refund').text(APP.formatMoney(maxRefund)).data('value', maxRefund);
            $('#refund_amount').attr('max', maxRefund);
        }
        $(document).on('input', '.return-qty, #refund_amount', recalc);
        recalc();

        $('#return_form').on('submit', function (e) {
            var any = $('.return-qty').filter(function () { return parseFloat($(this).val()) > 0; }).length;
            if (!any) {
                e.preventDefault();
                toastr.error('Enter a return quantity for at least one item.');
                setTimeout(function () { $('#return_form [type=submit]').prop('disabled', false); }, 10);
            }
        });
    });
</script>
@endpush
