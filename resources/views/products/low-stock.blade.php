@extends('layouts.app')

@section('title', 'Low Stock Alerts')
@section('page_subtitle', 'Products at or below their alert quantity')

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-triangle text-warning mr-1"></i> Products needing re-order</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="low_stock_table">
                <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Alert Qty</th>
                    <th>Shortfall</th>
                    <th>Cost Price</th>
                    <th class="no-export">Action</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#low_stock_table').DataTable({
            serverSide: true,
            ajax: '{{ route('products.low-stock') }}',
            order: [[3, 'asc']],
            columns: [
                { data: 'sku', name: 'sku' },
                { data: 'name', name: 'name' },
                { data: 'category_name', name: 'category_name', orderable: false, searchable: false },
                { data: 'stock_quantity', name: 'stock_quantity', className: 'text-center', searchable: false },
                { data: 'alert_quantity', name: 'alert_quantity', className: 'text-center', searchable: false },
                { data: 'shortfall', name: 'shortfall', className: 'text-center', orderable: false, searchable: false },
                { data: 'cost_price', name: 'cost_price', className: 'text-right', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
