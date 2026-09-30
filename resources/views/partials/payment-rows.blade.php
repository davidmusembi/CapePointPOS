{{--
    Payment rows on the sales invoice / purchase form (create and edit).
    Each row becomes (or updates) a payment allocated to the document. Params: $document (Sale|Purchase), $title, $hint.
    Totals / balance are computed by DocForm.recalc (doc-form.js).
--}}
@php
    $isEdit = isset($document) && $document->exists;
    $editable = $isEdit ? \App\Services\PaymentService::editableFor($document) : collect();
    $others = $isEdit
        ? $document->allocations()->with('payment')->get()->reject(fn ($a) => $editable->contains('id', $a->payment_id))
        : collect();
    $rows = old('payments', $editable->map(fn ($p) => [
        'id' => $p->id, 'amount' => (float) $p->amount, 'paid_on' => $p->date->toDateString(),
        'method' => $p->method, 'reference' => $p->reference, 'note' => $p->notes, 'payment_no' => $p->payment_no,
    ])->all());
    if (! $rows) {
        $rows = [['amount' => '', 'paid_on' => ($isEdit ? $document->date : now())->toDateString(), 'method' => array_key_first(payment_methods())]];
    }
@endphp
<div class="payment-panel mb-3" id="payment_rows_panel">
    <input type="hidden" name="payments_present" value="1">
    <div class="form-section-title mb-2"><i class="fas fa-money-bill-wave mr-1"></i> {{ $title ?? 'Add payment' }}</div>

    @if ($others->isNotEmpty())
        <div class="alert alert-light border small py-2">
            <i class="fas fa-lock text-muted mr-1"></i> Also allocated from the Payments module (edit them there):
            @foreach ($others as $a)
                <a href="{{ route('payments.show', $a->payment_id) }}" class="ml-1">{{ $a->payment->payment_no ?? '#' }}</a> ({{ money($a->amount) }})@if (! $loop->last),@endif
            @endforeach
        </div>
    @endif

    <div id="payment_rows" data-offset="{{ $others->sum('amount') }}">
        @foreach (array_values($rows) as $i => $row)
            <div class="payment-row">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small font-weight-600 text-muted payment-row-title">Payment {{ $i + 1 }}@if (! empty($row['payment_no'])) &middot; {{ $row['payment_no'] }}@endif</span>
                    <a href="#" class="small text-danger remove-payment-row {{ count($rows) === 1 && empty($row['id']) ? 'd-none' : '' }}"><i class="fas fa-times"></i> Remove</a>
                </div>
                @if (! empty($row['id']))<input type="hidden" name="payments[{{ $i }}][id]" value="{{ $row['id'] }}" class="pay-id">@endif
                <x-payment-fields :prefix="'payments['.$i.']'" :values="$row" />
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
    <small class="text-muted d-block mt-1">{{ $hint ?? 'Leave the amount at 0 to record the document on credit. Split payments across methods by adding rows.' }}</small>
</div>

@push('scripts')
<script>
    $(function () {
        var $wrap = $('#payment_rows');
        var next = $wrap.find('.payment-row').length;

        function renumber() {
            var $rows = $wrap.find('.payment-row');
            $rows.each(function (i) {
                var no = $(this).find('.payment-row-title').text().split('·')[1];
                $(this).find('.payment-row-title').text('Payment ' + (i + 1) + (no ? ' · ' + $.trim(no) : ''));
            });
            $rows.find('.remove-payment-row').removeClass('d-none');
            if ($rows.length === 1 && !$rows.find('.pay-id').length) { $rows.find('.remove-payment-row').addClass('d-none'); }
        }

        $('#add_payment_row').on('click', function () {
            var $first = $wrap.find('.payment-row').first();
            var $row = $first.clone();
            var i = next++;
            $row.find('.pay-id').remove();
            $row.find('[name]').each(function () { this.name = this.name.replace(/payments\[\d+\]/, 'payments[' + i + ']'); });
            $row.find('.pay-amount').val('');
            $row.find('input[type=text], textarea').val('');
            $row.find('.payment-row-title').text('Payment');
            $row.find('.invalid-feedback').remove();
            $row.find('.is-invalid').removeClass('is-invalid');
            $wrap.append($row);
            renumber();
            $row.find('.pay-amount').trigger('focus');
            if (window.DocForm) { DocForm.recalc(); }
        });

        $wrap.on('click', '.remove-payment-row', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.payment-row');
            if ($wrap.find('.payment-row').length === 1) {
                // keep one row: clear it (an existing payment is then removed on save)
                $row.find('.pay-id').remove();
                $row.find('.pay-amount').val('');
                $row.find('input[type=text], textarea').val('');
                $row.find('.payment-row-title').text('Payment 1');
            } else {
                $row.remove();
            }
            renumber();
            if (window.DocForm) { DocForm.recalc(); }
        });

        // "Pay balance": put whatever is still unpaid on the last row
        $('#btn_pay_full').on('click', function () {
            var $amounts = $wrap.find('.pay-amount');
            var others = parseFloat($wrap.data('offset')) || 0;
            $amounts.slice(0, -1).each(function () { others += Math.max(0, parseFloat($(this).val()) || 0); });
            var remaining = APP.round(Math.max(0, (window.DocForm ? DocForm.total : 0) - others));
            $amounts.last().val(remaining.toFixed(Math.min(2, APP.currency.decimals))).trigger('input');
        });
    });
</script>
@endpush
