@php
    $isCustomer = $type === 'customer';
    $party = $isCustomer ? $document->customer : $document->supplier;
@endphp
<div class="modal-dialog modal-lg" role="document">
    <form action="{{ route('payments.store') }}" method="POST" class="modal-content ajax-form" id="single_payment_form">
        @csrf
        <input type="hidden" name="party_type" value="{{ $type }}">
        <input type="hidden" name="{{ $type }}_id" value="{{ $party->id }}">
        <input type="hidden" name="from_modal" value="1">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-money-bill-wave text-teal mr-1"></i> Add Payment &middot; {{ $document->reference }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="row mb-3">
                <div class="col-md-4 mb-2">
                    <div class="summary-box primary">
                        <div class="label">{{ $isCustomer ? 'Customer' : 'Supplier' }}</div>
                        <div class="font-weight-600">{{ $party->display_name }}</div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="summary-box">
                        <div class="label">Total / Paid</div>
                        <div class="font-weight-600">{{ money($document->total) }} <small class="text-muted">/ {{ money($document->paid_amount) }}</small></div>
                        @if ($document->returned_amount > 0)<small class="text-muted">Returned: {{ money($document->returned_amount) }}</small>@endif
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="summary-box danger">
                        <div class="label">Balance due</div>
                        <div class="value">{{ money($document->due_amount) }}</div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="required">Amount</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $document->due_amount }}" name="amount" id="single_amount" class="form-control text-right font-weight-600" value="{{ number_format($document->due_amount, 2, '.', '') }}" required>
                        <input type="hidden" name="allocations[{{ $document->id }}]" id="single_alloc" value="{{ number_format($document->due_amount, 2, '.', '') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="required">Paid on</label>
                        <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="required">Payment method</label>
                        <select name="method" class="form-control custom-select" required>
                            @foreach (payment_methods() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Transaction reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="Bank ref / mobile money code / cheque no.">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control" value="Payment for {{ $document->reference }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Payment</button>
        </div>
    </form>
</div>
<script>
    $('#single_amount').on('input', function () { $('#single_alloc').val($(this).val()); });
    $('#single_payment_form').on('ajax:success', function () {
        // Detail pages (no server-side listing to refresh) reload to show new balances.
        if ($('[data-reload-on-payment]').length || !$('table.dataTable').length) { window.location.reload(); }
    });
</script>
