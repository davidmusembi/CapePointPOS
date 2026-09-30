@extends('layouts.app')

@section('title', 'Stock Report')
@section('page_subtitle', 'Current stock, valuation and movement summary')

@section('content')
    <x-filters>
        <div class="col-md-3">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control select2" data-allow-clear="true" data-placeholder="All categories">
                    <option value=""></option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Stock status</label>
                <select name="stock_status" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="in">In stock</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                </select>
            </div>
        </div>
        <x-date-range :start="$start" :end="$end" label="Movement period" />
    </x-filters>

    <div class="row mb-3">
        <div class="col-md-2 col-6 mb-2"><div class="summary-box primary"><div class="label">Products</div><div class="value" data-summary="items" data-format="raw">0</div></div></div>
        <div class="col-md-2 col-6 mb-2"><div class="summary-box primary"><div class="label">Units in stock</div><div class="value" data-summary="units" data-format="qty">0</div></div></div>
        <div class="col-md-2 col-6 mb-2"><div class="summary-box accent"><div class="label">Value at cost</div><div class="value" data-summary="value_cost">-</div></div></div>
        <div class="col-md-2 col-6 mb-2"><div class="summary-box accent"><div class="label">Value at retail</div><div class="value" data-summary="value_retail">-</div></div></div>
        <div class="col-md-2 col-6 mb-2"><div class="summary-box success"><div class="label">Potential profit</div><div class="value" data-summary="potential_profit">-</div></div></div>
        <div class="col-md-2 col-6 mb-2"><div class="summary-box danger"><div class="label">Low / out of stock</div><div class="value" data-summary="low_count" data-format="raw">0</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Stock valuation <small class="text-muted">(stock-managed products)</small></h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="stock_table">
                <thead>
                <tr><th>SKU</th><th>Product</th><th>Category</th><th>Unit</th><th>Current Stock</th><th>Alert Qty</th><th>Cost Price</th><th>Value (Cost)</th><th>Selling Price</th><th>Value (Retail)</th><th>Potential Profit</th><th>Status</th></tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="4" class="text-right">Total:</th>
                    <th class="text-right" data-total="units" data-format="qty"></th><th></th><th></th>
                    <th class="text-right" data-total="value_cost"></th><th></th>
                    <th class="text-right" data-total="value_retail"></th><th class="text-right" data-total="potential_profit"></th><th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card card-teal card-outline">
        <div class="card-header"><h3 class="card-title">Stock movement summary <small class="text-muted">(for the movement period)</small></h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="movements_table">
                <thead><tr><th>Product</th><th>SKU</th><th>Opening</th><th>Qty In</th><th>Qty Out</th><th>of which Sold</th><th>Closing</th></tr></thead>
                <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total:</th>
                    <th class="text-right" data-total="m_opening" data-format="qty"></th><th class="text-right" data-total="m_in" data-format="qty"></th>
                    <th class="text-right" data-total="m_out" data-format="qty"></th><th class="text-right" data-total="m_sold" data-format="qty"></th>
                    <th class="text-right" data-total="m_closing" data-format="qty"></th>
                </tr>
                </tfoot>
            </table>
            <small class="text-muted">In = purchases, sales returns, opening stock and positive adjustments. Out = sales, purchase returns and negative adjustments.</small>
        </div>
    </div>
@endsection

@push('scripts')
    @include('reports.partials.js')
    <script>
        $(function () {
            RPT.init();
            var url = '{{ route('reports.stock') }}', r = 'text-right';

            RPT.server('#stock_table', url, 'products', [
                { data: 'sku', name: 'sku' },
                { data: 'name', name: 'name' },
                { data: 'category_name', name: 'category_name', orderable: false },
                { data: 'unit_name', name: 'unit_name', orderable: false, searchable: false },
                { data: 'stock_quantity', name: 'stock_quantity', render: RPT.qty, className: r, searchable: false },
                { data: 'alert_quantity', name: 'alert_quantity', render: RPT.qty, className: r, searchable: false },
                { data: 'cost_price', name: 'cost_price', render: RPT.money, className: r, searchable: false },
                { data: 'value_cost', name: 'value_cost', render: RPT.money, className: r, orderable: false, searchable: false },
                { data: 'selling_price', name: 'selling_price', render: RPT.money, className: r, searchable: false },
                { data: 'value_retail', name: 'value_retail', render: RPT.money, className: r, orderable: false, searchable: false },
                { data: 'potential_profit', name: 'potential_profit', render: RPT.money, className: r, orderable: false, searchable: false },
                { data: 'status', name: 'status', orderable: false, searchable: false }
            ], { order: [[1, 'asc']] });

            RPT.client('#movements_table', url, 'movements', [
                { data: 'product' }, { data: 'sku' },
                { data: 'opening', render: RPT.qty, className: r }, { data: 'qty_in', render: RPT.qty, className: r },
                { data: 'qty_out', render: RPT.qty, className: r }, { data: 'sold', render: RPT.qty, className: r },
                { data: 'closing', render: RPT.qty, className: r + ' font-weight-bold' }
            ], { order: [[0, 'asc']] });
        });
    </script>
@endpush
