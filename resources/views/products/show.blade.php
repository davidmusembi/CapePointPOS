@extends('layouts.app')

@section('title', $product->name)
@section('page_subtitle', $product->sku)

@section('header_actions')
    <a href="{{ route('products.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    @can('stock_adjustments.create')
        <a href="{{ route('stock-adjustments.create', ['product_id' => $product->id]) }}" class="btn btn-light-primary"><i class="fas fa-sliders-h"></i> Adjust Stock</a>
    @endcan
    @can('products.edit')
        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
    @endcan
@endsection

@section('content')
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile tone-teal">
                <div class="stat-icon"><i class="fas fa-cubes"></i></div>
                <div>
                    <div class="stat-label">Current stock</div>
                    <div class="stat-value">{{ $product->track_stock ? qty_format($product->stock_quantity).' '.($product->unit->short_name ?? '') : 'N/A' }}</div>
                    @if ($product->is_low_stock)<div class="stat-sub text-danger"><i class="fas fa-exclamation-triangle"></i> Below alert level ({{ qty_format($product->alert_quantity) }})</div>@endif
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile tone-blue">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="stat-label">Stock value (cost)</div>
                    <div class="stat-value">{{ money($product->track_stock ? $product->stock_quantity * $product->cost_price : 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile tone-green">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div>
                    <div class="stat-label">Net units sold</div>
                    <div class="stat-value">{{ qty_format($stats['sold']) }}</div>
                    <div class="stat-sub">Revenue {{ money($stats['revenue']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile tone-violet">
                <div class="stat-icon"><i class="fas fa-truck-loading"></i></div>
                <div>
                    <div class="stat-label">Net units purchased</div>
                    <div class="stat-value">{{ qty_format($stats['purchased']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Product information</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm info-list mb-0">
                        <tr><th class="pl-3">SKU</th><td>{{ $product->sku }}</td></tr>
                        <tr><th class="pl-3">Barcode</th><td>{{ $product->barcode ?: '-' }}</td></tr>
                        <tr><th class="pl-3">Category</th><td>{{ $product->category->name ?? '-' }}</td></tr>
                        <tr><th class="pl-3">Unit</th><td>{{ $product->unit->name ?? '-' }}</td></tr>
                        <tr><th class="pl-3">Tax</th><td>{{ $product->taxRate?->label ?? 'None' }}</td></tr>
                        <tr><th class="pl-3">Cost price</th><td>{{ money($product->cost_price) }}</td></tr>
                        <tr><th class="pl-3">Selling price</th><td>{{ money($product->selling_price) }}</td></tr>
                        <tr><th class="pl-3">Margin</th><td>{{ money($product->selling_price - $product->cost_price) }}
                                @if ($product->selling_price > 0)<small class="text-muted">({{ round(($product->selling_price - $product->cost_price) / $product->selling_price * 100, 1) }}%)</small>@endif</td></tr>
                        <tr><th class="pl-3">Alert qty</th><td>{{ qty_format($product->alert_quantity) }}</td></tr>
                        <tr><th class="pl-3">Status</th><td>{!! status_badge($product->is_active ? 'active' : 'inactive') !!}</td></tr>
                        <tr><th class="pl-3">Added by</th><td>{{ $product->creator->name ?? '-' }} <small class="text-muted">{{ format_date($product->created_at) }}</small></td></tr>
                    </table>
                    @if ($product->description)
                        <div class="p-3 border-top small text-muted">{{ $product->description }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card card-teal card-outline">
                <div class="card-header"><h3 class="card-title">Stock history</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped table-sm w-100" id="stock_history_table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Qty In</th>
                            <th>Qty Out</th>
                            <th>Balance</th>
                            <th>Unit Cost</th>
                            <th>By</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#stock_history_table').DataTable({
            serverSide: true,
            ajax: '{{ route('products.stock-history', $product) }}',
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'date' },
                { data: 'type', name: 'type' },
                { data: 'reference_no', name: 'reference_no' },
                { data: 'qty_in', name: 'quantity', className: 'text-right', searchable: false },
                { data: 'qty_out', name: 'quantity', className: 'text-right', orderable: false, searchable: false },
                { data: 'balance_after', name: 'balance_after', className: 'text-right', searchable: false },
                { data: 'unit_cost', name: 'unit_cost', className: 'text-right', searchable: false },
                { data: 'user', name: 'user', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
