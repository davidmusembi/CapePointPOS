@extends('layouts.app')

@section('title', 'Products')
@section('page_subtitle', 'Manage your products')

@section('header_actions')
    @can('products.create')
        <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
    @endcan
@endsection

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
                <label>Unit</label>
                <select name="unit_id" class="form-control select2" data-allow-clear="true" data-placeholder="All units">
                    <option value=""></option>
                    @foreach ($units as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Stock level</label>
                <select name="stock" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">All Products</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="products_table">
                <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Cost Price</th>
                    <th>Selling Price</th>
                    <th>Tax</th>
                    <th>Current Stock</th>
                    <th>Stock Value</th>
                    <th class="no-export" style="width:90px">Action</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var table = $('#products_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('products.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[1, 'asc']],
            columns: [
                { data: 'sku', name: 'sku' },
                { data: 'name', name: 'name' },
                { data: 'category_name', name: 'category_name', orderable: false },
                { data: 'cost_price', name: 'cost_price', className: 'text-right' },
                { data: 'selling_price', name: 'selling_price', className: 'text-right' },
                { data: 'tax', name: 'tax', orderable: false, searchable: false },
                { data: 'stock_quantity', name: 'stock_quantity', className: 'text-center', searchable: false },
                { data: 'stock_value', name: 'stock_value', className: 'text-right', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
        $('#filters_form').on('change', 'select, input', function () { table.ajax.reload(); });
    });
</script>
@endpush
