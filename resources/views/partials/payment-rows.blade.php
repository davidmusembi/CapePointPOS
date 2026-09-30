{{--
    Split-payment rows captured on a new sales invoice / purchase (each row becomes a payment allocated to the document).
    Params: $title (panel title), $hint (help text). Totals / balance are computed by DocForm.recalc (doc-form.js).
--}}
@php
    $rows = old('payments', [['amount' => '', 'paid_on' => now()->toDateString(), 'method' => array_key_first(payment_methods()), 'reference' => '', 'note' => '']]);
@endphp
<div class="payment-panel mb-3" id="payment_rows_panel">
    <div class="d-flex justify-content-between align-items-center">
        <div class="form-section-title mb-2 flex-grow-1"><i class="fas fa-money-bill-wave mr-1"></i> {{ $title ?? 'Add payment' }}</div>
    </div>

    <div id="payment_rows">
        @foreach (array_values($rows) as $i => $row)
            <div class="payment-row" data-index="{{ $i }}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small font-weight-600 text-muted payment-row-title">Payment {{ $i + 1 }}</span>
                    <a href="#" class="small text-danger remove-payment-row {{ $i === 0 ? 'd-none' : '' }}"><i class="fas fa-times"></i> Remove</a>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required">Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-money-bill-alt"></i></span></div>
                                <input type="number" step="any" min="0" name="payments[{{ $i }}][amount]" class="form-control text-right pay-amount" value="{{ $row['amount'] ?? '' }}" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required">Paid on</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-calendar-alt"></i></span></div>
                                <input type="date" name="payments[{{ $i }}][paid_on]" class="form-control pay-date" value="{{ $row['paid_on'] ?? now()->toDateString() }}" max="{{ now()->addDay()->toDateString() }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required">Payment method</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-credit-card"></i></span></div>
                                <select name="payments[{{ $i }}][method]" class="form-control custom-select pay-method">
                                    @foreach (payment_methods() as $key => $label)
                                        <option value="{{ $key }}" @selected(($row['method'] ?? '') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Transaction reference</label>
                            <input type="text" name="payments[{{ $i }}][reference]" class="form-control" maxlength="190" value="{{ $row['reference'] ?? '' }}" placeholder="Bank ref / mobile money code / cheque no.">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Payment note</label>
                            <textarea name="payments[{{ $i }}][note]" rows="2" class="form-control" maxlength="1000">{{ $row['note'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center border-top pt-2" style="gap:.5rem">
        <div>
            <button type="button" class="btn btn-sm btn-light-primary" id="add_payment_row"><i class="fas fa-plus"></i> Add payment row</button>
            <button type="button" class="btn btn-sm btn-teal" id="btn_pay_full" title="Put the remaining balance on the last payment row">Pay balance</button>
        </div>
        <div class="text-right small">
            Total paying: <strong id="paying_text">0.00</strong> &middot;
            Balance: <strong id="balance_text">0.00</strong>
            <span id="payment_status_preview" class="badge badge-pill badge-danger ml-1">Due</span>
        </div>
    </div>
    <small class="text-muted d-block mt-1">{{ $hint ?? 'Leave the amount at 0 to record the whole document on credit. Split payments across several methods by adding rows.' }}</small>
</div>

@push('scripts')
<script>
    $(function () {
        var $wrap = $('#payment_rows');
        var next = $wrap.find('.payment-row').length;

        function renumber() {
            $wrap.find('.payment-row').each(function (i) {
                $(this).find('.payment-row-title').text('Payment ' + (i + 1));
                $(this).find('.remove-payment-row').toggleClass('d-none', i === 0);
            });
        }

        $('#add_payment_row').on('click', function () {
            var $first = $wrap.find('.payment-row').first();
            var $row = $first.clone();
            var i = next++;
            $row.attr('data-index', i);
            $row.find('[name]').each(function () {
                this.name = this.name.replace(/payments\[\d+\]/, 'payments[' + i + ']');
            });
            $row.find('.pay-amount').val('');
            $row.find('input[type=text], textarea').val('');
            $row.find('.pay-date').val($first.find('.pay-date').val());
            $row.find('.invalid-feedback').remove();
            $row.find('.is-invalid').removeClass('is-invalid');
            $wrap.append($row);
            renumber();
            $row.find('.pay-amount').trigger('focus');
            if (window.DocForm) { DocForm.recalc(); }
        });

        $wrap.on('click', '.remove-payment-row', function (e) {
            e.preventDefault();
            $(this).closest('.payment-row').remove();
            renumber();
            if (window.DocForm) { DocForm.recalc(); }
        });

        // "Pay balance": put whatever is still unpaid on the last row
        $('#btn_pay_full').on('click', function () {
            var $amounts = $wrap.find('.pay-amount');
            var others = 0;
            $amounts.slice(0, -1).each(function () { others += Math.max(0, parseFloat($(this).val()) || 0); });
            var remaining = APP.round(Math.max(0, (window.DocForm ? DocForm.total : 0) - others));
            $amounts.last().val(remaining.toFixed(Math.min(2, APP.currency.decimals))).trigger('input');
        });
    });
</script>
@endpush
