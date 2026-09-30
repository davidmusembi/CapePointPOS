@extends('layouts.app')

@section('title', $purchase->exists ? 'Edit Purchase '.$purchase->purchase_no : ($order ? 'Receive Goods: '.$order->lpo_no : 'Add Purchase / GRN'))

@section('header_actions')
    <a href="{{ $order ? route('purchase-orders.show', $order) : route('purchases.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    @if ($order)
        <div class="alert alert-info">
            <i class="fas fa-info-circle mr-1"></i>
            Receiving goods against LPO <a href="{{ route('purchase-orders.show', $order) }}" class="font-weight-bold">{{ $order->lpo_no }}</a>
            dated {{ format_date($order->date) }}. Quantities default to the pending balance &mdash; reduce them for a partial delivery.
        </div>
    @endif

    <form id="doc-form" action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if ($purchase->exists) @method('PUT') @endif
        @if ($order)
            <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
        @endif

        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required" for="supplier_id">Supplier</label>
                            @if ($order)
                                <input type="hidden" name="supplier_id" value="{{ $order->supplier_id }}">
                                <input type="text" class="form-control" value="{{ $order->supplier->display_name ?? '' }}" readonly>
                            @else
                                <select name="supplier_id" id="supplier_id" class="form-control" required data-placeholder="Search supplier">
                                    @if ($supplier)
                                        <option value="{{ $supplier->id }}" selected data-terms="{{ $supplier->payment_terms }}">{{ $supplier->display_name }}</option>
                                    @endif
                                </select>
                            @endif
                            @error('supplier_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="supplier_invoice_no">Supplier invoice no.</label>
                            <input type="text" name="supplier_invoice_no" id="supplier_invoice_no" class="form-control" value="{{ old('supplier_invoice_no', $purchase->supplier_invoice_no) }}" maxlength="60">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="required" for="date">Purchase date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', optional($purchase->date)->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="payment_terms">Pay term (days)</label>
                            <input type="number" min="0" max="365" name="payment_terms" id="payment_terms" class="form-control" value="{{ old('payment_terms', $purchase->payment_terms ?? $purchase->supplier?->payment_terms ?? settings('default_payment_terms')) }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="due_date">Due date</label>
                            <input type="date" name="due_date" id="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date', optional($purchase->due_date)->toDateString()) }}">
                        </div>
                    </div>
                    @if ($purchase->exists)
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Payment status</label>
                                <div class="pt-1">{!! payment_status_badge($purchase->payment_status) !!} <small class="text-muted">Paid {{ money($purchase->paid_amount) }}</small></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @include('partials.shipping', ['doc' => $purchase, 'docType' => 'purchase'])

        @include('purchases._items', ['discountType' => $purchase->discount_type ?? 'fixed', 'discountValue' => $purchase->discount_value ?? 0, 'notes' => $purchase->notes, 'withPayment' => true, 'withShipping' => true, 'document' => $purchase])

        <div class="text-right mb-4">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> {{ $purchase->exists ? 'Update Purchase' : 'Save Purchase' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script src="{{ asset('js/doc-form.js') }}?v={{ filemtime(public_path('js/doc-form.js')) }}"></script>
<script>
    $(function () {
        // Pay term drives the due date; the due date can still be overridden manually.
        function setDueDate() {
            var d = $('#date').val(), days = parseInt($('#payment_terms').val(), 10);
            if (!d || isNaN(days)) { return; }
            $('#due_date').val(moment(d).add(days, 'days').format('YYYY-MM-DD'));
        }
        $('#date, #payment_terms').on('change input', setDueDate);
        if (!$('#due_date').val()) { setDueDate(); }

        if ($('#supplier_id').length) {
            APP.contactSelect('#supplier_id', '{{ route('suppliers.search') }}').on('select2:select', function (e) {
                var t = e.params.data.payment_terms;
                if (t !== undefined && t !== null && t !== '') { $('#payment_terms').val(parseInt(t, 10)); }
                setDueDate();
            });
        }

        DocForm.init({
            priceName: 'unit_cost',
            priceField: 'cost_price',
            checkStock: false,
            searchUrl: '{{ route('products.search') }}',
            taxRates: @json($taxRates),
            items: @json($items)
        });
    });
</script>
@endpush
