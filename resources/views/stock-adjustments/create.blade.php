@extends('layouts.app')

@section('title', 'Add Stock Adjustment')

@section('header_actions')
    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    <form action="{{ route('stock-adjustments.store') }}" method="POST" id="adjustment_form">
        @csrf
        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="required">Date</label>
                            <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="required">Adjustment type</label>
                            <select name="type" id="adj_type" class="form-control custom-select" required>
                                <option value="decrease" @selected(old('type', 'decrease') === 'decrease')>Decrease (stock out)</option>
                                <option value="increase" @selected(old('type') === 'increase')>Increase (stock in)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="required">Reason</label>
                            <select name="reason" class="form-control custom-select" required>
                                @foreach ($reasons as $r)
                                    <option value="{{ $r }}" @selected(old('reason') === $r)>{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Notes</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Products</div>
                <div class="row justify-content-center mb-3">
                    <div class="col-md-8 product-search-wrap">
                        <select id="product_search" class="form-control"></select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered items-table" id="adj_items">
                        <thead>
                        <tr><th>Product</th><th style="width:130px">Current stock</th><th style="width:140px">Quantity</th><th style="width:160px">Avg. unit cost</th><th style="width:150px" class="text-right">Value</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody><tr class="empty-row"><td colspan="6">Search and add products above</td></tr></tbody>
                        <tfoot><tr><th colspan="4" class="text-right">Total value</th><th class="text-right" id="adj_total">0.00</th><th></th></tr></tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save adjustment</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $(function () {
        var idx = 0, $tbody = $('#adj_items tbody');

        function recalc() {
            var total = 0, dec = $('#adj_type').val() === 'decrease';
            $tbody.find('tr.item-row').each(function () {
                var q = parseFloat($(this).find('.qty').val()) || 0, c = parseFloat($(this).find('.cost').val()) || 0;
                total += q * c;
                $(this).find('.value').text(APP.formatNumber(q * c));
                $(this).toggleClass('table-warning', dec && $(this).data('track') == 1 && q > parseFloat($(this).data('stock')));
            });
            $('#adj_total').text(APP.formatMoney(total));
            if (!$tbody.find('tr.item-row').length && !$tbody.find('.empty-row').length) {
                $tbody.html('<tr class="empty-row"><td colspan="6">Search and add products above</td></tr>');
            }
        }

        function addRow(p) {
            if ($tbody.find('tr[data-id="' + p.id + '"]').length) { toastr.info('Product already added'); return; }
            if (!p.track_stock) { toastr.warning(p.text + ' does not track stock.'); return; }
            $tbody.find('.empty-row').remove();
            var i = idx++;
            $tbody.append('<tr class="item-row" data-id="' + p.id + '" data-stock="' + p.stock + '" data-track="' + (p.track_stock ? 1 : 0) + '">' +
                '<td><div class="product-name">' + APP.escape(p.text) + '</div><div class="product-meta">' + APP.escape(p.sku) + '</div>' +
                '<input type="hidden" name="items[' + i + '][product_id]" value="' + p.id + '"></td>' +
                '<td class="text-center">' + APP.formatQty(p.stock) + ' ' + APP.escape(p.unit || '') + '</td>' +
                '<td><input type="number" step="any" min="0.001" class="form-control qty text-right" name="items[' + i + '][quantity]" value="1" required></td>' +
                '<td><input type="number" class="form-control cost text-right" value="' + p.cost_price + '" readonly tabindex="-1" title="Current average cost"></td>' +
                '<td class="text-right value font-weight-600"></td>' +
                '<td class="text-center"><i class="fas fa-times-circle remove-row"></i></td></tr>');
            recalc();
            $tbody.find('tr.item-row:last .qty').focus().select();
        }

        $('#product_search').select2({
            theme: 'bootstrap4', width: '100%', placeholder: 'Search product by name or SKU...',
            ajax: { url: '{{ route('products.search') }}', dataType: 'json', delay: 200, data: function (p) { return { q: p.term, page: p.page || 1 }; }, processResults: function (d) { return { results: d.results, pagination: { more: d.more } }; } },
            templateResult: function (p) { if (p.loading) { return p.text; } return $('<div class="select2-result-product"><strong>' + APP.escape(p.text) + '</strong><br><small>' + APP.escape(p.sku) + ' &middot; Stock: ' + APP.formatQty(p.stock) + '</small></div>'); }
        }).on('select2:select', function (e) { addRow(e.params.data); $(this).val(null).trigger('change'); });

        $(document).on('input', '#adj_items .qty, #adj_items .cost', recalc);
        $('#adj_type').on('change', recalc);
        $(document).on('click', '#adj_items .remove-row', function () { $(this).closest('tr').remove(); recalc(); });
        $('#adjustment_form').on('submit', function (e) {
            if (!$tbody.find('tr.item-row').length) { e.preventDefault(); toastr.error('Add at least one product.'); setTimeout(function () { $('#adjustment_form [type=submit]').prop('disabled', false); }, 10); }
        });

        @if ($product)
            @php
                $initial = [
                    'id' => $product->id, 'text' => $product->name, 'sku' => $product->sku,
                    'stock' => (float) $product->stock_quantity, 'unit' => $product->unit->short_name ?? '',
                    'cost_price' => round($product->cost_price, 2), 'track_stock' => $product->track_stock,
                ];
            @endphp
            addRow({!! json_encode($initial) !!});
        @endif
    });
</script>
@endpush
