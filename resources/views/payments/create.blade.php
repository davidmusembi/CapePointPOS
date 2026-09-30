@extends('layouts.app')

@php $isCustomer = $type === 'customer'; @endphp

@section('title', $isCustomer ? 'Receive Payment' : 'Pay Supplier')
@section('page_subtitle', $isCustomer ? 'Record a customer receipt and allocate it to invoices' : 'Record a supplier payment and allocate it to purchases')

@section('header_actions')
    <a href="{{ route('payments.index', ['type' => $type]) }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    <form action="{{ route('payments.store') }}" method="POST" id="payment_form">
        @csrf
        <input type="hidden" name="party_type" value="{{ $type }}">

        <div class="row">
            <div class="col-lg-4">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Payment details</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="required">{{ $isCustomer ? 'Customer' : 'Supplier' }}</label>
                            <select name="{{ $type }}_id" id="party_id" class="form-control" required>
                                @if ($party)
                                    <option value="{{ $party->id }}" selected>{{ $party->display_name }}</option>
                                @endif
                            </select>
                            <div class="mt-2 small" id="party_balance_wrap" style="{{ $party ? '' : 'display:none' }}">
                                Account balance: <strong id="party_balance">{{ $party ? money($party->balance) : '' }}</strong>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label class="required">Date</label>
                                <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                            </div>
                            <div class="form-group col-6">
                                <label class="required">Method</label>
                                <select name="method" class="form-control custom-select" required>
                                    @foreach (payment_methods() as $key => $label)
                                        <option value="{{ $key }}" @selected(old('method', 'bank') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="required">Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">{{ settings('currency_symbol') }}</span></div>
                                <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control form-control-lg text-right font-weight-600" value="{{ old('amount') }}" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Transaction reference</label>
                            <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="Bank ref / mobile money code / cheque no.">
                        </div>
                        <div class="form-group mb-0">
                            <label>Notes</label>
                            <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card card-teal card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Allocate to {{ $isCustomer ? 'invoices' : 'purchases' }}</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-light-primary" id="btn_auto_allocate"><i class="fas fa-magic"></i> Auto-allocate (oldest first)</button>
                            <button type="button" class="btn btn-sm btn-default" id="btn_clear_allocation">Clear</button>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-hover mb-0" id="allocation_table">
                            <thead>
                            <tr>
                                <th class="pl-3">{{ $isCustomer ? 'Invoice' : 'Purchase' }}</th>
                                <th>Date</th>
                                <th>Due date</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Paid</th>
                                <th class="text-right">Balance due</th>
                                <th class="text-right pr-3" style="width:170px">Allocate</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr><td colspan="7" class="empty-state">Select a {{ $isCustomer ? 'customer' : 'supplier' }} to load outstanding documents.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <div class="row text-center">
                            <div class="col-4"><div class="text-muted small">Payment amount</div><div class="font-weight-bold" id="sum_amount">{{ money(0) }}</div></div>
                            <div class="col-4"><div class="text-muted small">Allocated</div><div class="font-weight-bold text-primary" id="sum_allocated">{{ money(0) }}</div></div>
                            <div class="col-4"><div class="text-muted small">Unallocated (advance / credit)</div><div class="font-weight-bold" id="sum_unallocated">{{ money(0) }}</div></div>
                        </div>
                    </div>
                </div>

                <div class="text-right">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check"></i> Save Payment</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $(function () {
        var type = @json($type);
        var $tbody = $('#allocation_table tbody');

        function totals() {
            var amount = parseFloat($('#amount').val()) || 0, allocated = 0;
            $tbody.find('.alloc').each(function () { allocated += parseFloat($(this).val()) || 0; });
            allocated = APP.round(allocated);
            $('#sum_amount').text(APP.formatMoney(amount));
            $('#sum_allocated').text(APP.formatMoney(allocated));
            var un = APP.round(amount - allocated);
            $('#sum_unallocated').text(APP.formatMoney(un)).toggleClass('text-danger', un < 0).toggleClass('text-warning', un > 0);
            return { amount: amount, allocated: allocated };
        }

        function load(partyId) {
            if (!partyId) { return; }
            $tbody.html('<tr><td colspan="7" class="empty-state"><i class="fas fa-circle-notch fa-spin"></i> Loading...</td></tr>');
            $.getJSON('{{ route('payments.outstanding') }}', { type: type, party_id: partyId }, function (res) {
                $('#party_balance').text(APP.formatMoney(res.balance));
                $('#party_balance_wrap').show();
                if (!res.documents.length) {
                    $tbody.html('<tr><td colspan="7" class="empty-state"><i class="fas fa-check-circle text-success d-block"></i>No outstanding documents. The payment will be kept as an advance / credit.</td></tr>');
                    totals();
                    return;
                }
                var html = '';
                $.each(res.documents, function (i, d) {
                    html += '<tr data-due="' + d.due + '">' +
                        '<td class="pl-3"><a href="' + d.url + '" target="_blank">' + APP.escape(d.reference) + '</a>' + (d.supplier_invoice_no ? '<br><small class="text-muted">' + APP.escape(d.supplier_invoice_no) + '</small>' : '') + '</td>' +
                        '<td>' + d.date + '</td>' +
                        '<td class="' + (d.overdue ? 'text-danger font-weight-600' : '') + '">' + d.due_date + (d.overdue ? ' <i class="fas fa-exclamation-circle"></i>' : '') + '</td>' +
                        '<td class="text-right">' + APP.formatMoney(d.total) + '</td>' +
                        '<td class="text-right">' + APP.formatMoney(d.paid) + '</td>' +
                        '<td class="text-right font-weight-600">' + APP.formatMoney(d.due) + '</td>' +
                        '<td class="pr-3"><input type="number" step="0.01" min="0" max="' + d.due + '" name="allocations[' + d.id + ']" class="form-control form-control-sm text-right alloc" placeholder="0.00"></td>' +
                        '</tr>';
                });
                $tbody.html(html);
                totals();
            });
        }

        APP.contactSelect('#party_id', '{{ route($type === 'customer' ? 'customers.search' : 'suppliers.search') }}')
            .on('select2:select', function (e) { load(e.params.data.id); });

        $('#btn_auto_allocate').on('click', function () {
            var remaining = parseFloat($('#amount').val()) || 0;
            if (remaining <= 0) {
                // no amount yet: pay everything outstanding
                var sum = 0;
                $tbody.find('tr[data-due]').each(function () { sum += parseFloat($(this).data('due')); });
                $('#amount').val(APP.round(sum));
                remaining = sum;
            }
            $tbody.find('tr[data-due]').each(function () {
                var due = parseFloat($(this).data('due'));
                var v = APP.round(Math.min(due, Math.max(0, remaining)));
                $(this).find('.alloc').val(v > 0 ? v.toFixed(2) : '');
                remaining = APP.round(remaining - v);
            });
            totals();
        });
        $('#btn_clear_allocation').on('click', function () { $tbody.find('.alloc').val(''); totals(); });
        $(document).on('input', '#amount, .alloc', function () {
            var $i = $(this);
            if ($i.hasClass('alloc') && parseFloat($i.val()) > parseFloat($i.attr('max'))) { $i.val($i.attr('max')); }
            totals();
        });

        $('#payment_form').on('submit', function (e) {
            var t = totals();
            if (t.allocated - t.amount > 0.009) {
                e.preventDefault();
                toastr.error('Allocated amount exceeds the payment amount.');
                setTimeout(function () { $('#payment_form [type=submit]').prop('disabled', false); }, 10);
            }
        });

        @if ($party) load({{ $party->id }}); @endif
    });
</script>
@endpush
