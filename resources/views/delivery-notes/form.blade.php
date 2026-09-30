@extends('layouts.app')

@php
    $editing = $note->exists;
    $initialItems = $items;
    if (is_array(old('items'))) {
        $products = \App\Models\Product::withTrashed()->with('unit')->whereIn('id', collect(old('items'))->pluck('product_id'))->get()->keyBy('id');
        $initialItems = collect(old('items'))->map(fn ($r) => isset($products[$r['product_id'] ?? 0]) ? [
            'id' => $products[$r['product_id']]->id, 'text' => $products[$r['product_id']]->name, 'sku' => $products[$r['product_id']]->sku,
            'unit' => $products[$r['product_id']]->unit->short_name ?? '', 'description' => $r['description'] ?? null, 'quantity' => (float) ($r['quantity'] ?? 1),
        ] : null)->filter()->values()->all();
    }
@endphp

@section('title', $editing ? 'Edit Delivery Note '.$note->delivery_no : 'New Delivery Note')
@section('page_subtitle', $sale ? 'For invoice '.$sale->invoice_no : 'Standalone')

@section('header_actions')
    <a href="{{ $editing ? route('delivery-notes.show', $note) : ($sale ? route('sales.show', $sale) : route('delivery-notes.index')) }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    <form action="{{ $editing ? route('delivery-notes.update', $note) : route('delivery-notes.store') }}" method="POST" id="dn_form">
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($sale)<input type="hidden" name="sale_id" value="{{ $sale->id }}">@endif

        @unless ($sale)
            <div class="alert alert-light border"><i class="fas fa-info-circle text-primary mr-1"></i>
                Delivery notes do not change stock. Stock is deducted by the sales invoice; a standalone delivery note is only a dispatch document.
            </div>
        @endunless

        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required" for="customer_id">Customer</label>
                            @if ($sale)
                                <input type="hidden" name="customer_id" value="{{ $sale->customer_id }}">
                                <input type="text" class="form-control" value="{{ $sale->customer->display_name }}" readonly>
                            @else
                                <select name="customer_id" id="customer_id" class="form-control" required data-placeholder="Search customer">
                                    @php $cust = old('customer_id') ? \App\Models\Customer::find(old('customer_id')) : $customer; @endphp
                                    @if ($cust)<option value="{{ $cust->id }}" selected>{{ $cust->display_name }}</option>@endif
                                </select>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="required" for="date">Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', optional($note->date)->toDateString() ?? now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="required" for="status">Status</label>
                            <select name="status" id="status" class="form-control custom-select">
                                @foreach (\App\Models\DeliveryNote::STATUSES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', $note->status) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="delivery_address">Delivery address</label>
                            <input type="text" name="delivery_address" id="delivery_address" class="form-control" value="{{ old('delivery_address', $note->delivery_address) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="contact_person">Contact person</label>
                            <input type="text" name="contact_person" id="contact_person" class="form-control" value="{{ old('contact_person', $note->contact_person) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="contact_phone">Contact phone</label>
                            <input type="text" name="contact_phone" id="contact_phone" class="form-control" value="{{ old('contact_phone', $note->contact_phone) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="driver_name">Driver</label>
                            <input type="text" name="driver_name" id="driver_name" class="form-control" value="{{ old('driver_name', $note->driver_name) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="vehicle_no">Vehicle No.</label>
                            <input type="text" name="vehicle_no" id="vehicle_no" class="form-control" value="{{ old('vehicle_no', $note->vehicle_no) }}" maxlength="30">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-teal card-outline">
            <div class="card-header"><h3 class="card-title">Items</h3></div>
            <div class="card-body">
                <div class="row justify-content-center mb-3">
                    <div class="col-md-8 product-search-wrap">
                        <select id="dn_product_search" class="form-control"></select>
                    </div>
                </div>
                <table class="table table-bordered items-table" id="dn_items">
                    <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Product</th>
                        <th>Description</th>
                        <th class="text-right" style="width:140px">Quantity</th>
                        <th style="width:36px"></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="form-group mb-0 mt-3">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes', $note->notes) }}</textarea>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ $editing ? 'Update' : 'Save' }} Delivery Note</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $(function () {
        var idx = 0;
        function renumber() {
            var $rows = $('#dn_items tbody tr.item-row');
            $rows.each(function (i) { $(this).find('.row-no').text(i + 1); });
            if (!$rows.length && !$('#dn_items tr.empty-row').length) {
                $('#dn_items tbody').html('<tr class="empty-row"><td colspan="5"><i class="fas fa-box-open mr-1"></i> Search and add products above</td></tr>');
            }
        }
        function addRow(p) {
            $('#dn_items tr.empty-row').remove();
            var n = 'items[' + (idx++) + ']';
            $('#dn_items tbody').append(
                '<tr class="item-row">' +
                '<td class="row-no text-center"></td>' +
                '<td><div class="product-name">' + APP.escape(p.text) + '</div><div class="product-meta">' + APP.escape(p.sku || '') + '</div>' +
                '<input type="hidden" name="' + n + '[product_id]" value="' + p.id + '"></td>' +
                '<td><input type="text" class="form-control" name="' + n + '[description]" value="' + APP.escape(p.description || '') + '"></td>' +
                '<td><div class="input-group"><input type="number" step="any" min="0.001" class="form-control text-right" name="' + n + '[quantity]" value="' + (p.quantity || 1) + '" required>' +
                '<div class="input-group-append"><span class="input-group-text">' + APP.escape(p.unit || '') + '</span></div></div></td>' +
                '<td class="text-center"><i class="fas fa-times-circle remove-row"></i></td></tr>');
            renumber();
        }

        $.each(@json($initialItems), function (i, it) { addRow(it); });
        renumber();

        $('#dn_product_search').select2({
            theme: 'bootstrap4', width: '100%', placeholder: 'Search product to add...',
            ajax: {
                url: '{{ route('products.search') }}', dataType: 'json', delay: 200,
                data: function (p) { return { q: p.term, page: p.page || 1 }; },
                processResults: function (d) { return { results: d.results, pagination: { more: d.more } }; }
            }
        }).on('select2:select', function (e) { addRow(e.params.data); $(this).val(null).trigger('change'); });

        $(document).on('click', '#dn_items .remove-row', function () { $(this).closest('tr').remove(); renumber(); });

        if ($('#customer_id').length) {
            APP.contactSelect('#customer_id', '{{ route('customers.search') }}').on('select2:select', function (e) {
                var c = e.params.data;
                if (!$('#contact_person').val()) { $('#contact_person').val(c.text); }
                if (!$('#contact_phone').val() && c.phone) { $('#contact_phone').val(c.phone); }
                if (!$('#delivery_address').val() && c.address) { $('#delivery_address').val(c.address); }
            });
        }

        $('#dn_form').on('submit', function (e) {
            if (!$('#dn_items tr.item-row').length) {
                e.preventDefault();
                toastr.error('Add at least one item.');
                setTimeout(function () { $('#dn_form [type=submit]').prop('disabled', false); }, 10);
            }
        });
    });
</script>
@endpush
