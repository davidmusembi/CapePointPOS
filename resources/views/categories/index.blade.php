@extends('layouts.app')

@section('title', 'Categories')
@section('page_subtitle', 'Manage product categories')

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">All Categories</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-sm btn-primary btn-modal" data-href="{{ route('categories.create') }}">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="categories_table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Products</th>
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
        $('#categories_table').DataTable({
            serverSide: true,
            ajax: '{{ route('categories.index') }}',
            order: [[0, 'asc']],
            columns: [
                { data: 'name', name: 'name' },
                { data: 'code', name: 'code' },
                { data: 'description', name: 'description' },
                { data: 'products_count', name: 'products_count', searchable: false, className: 'text-center' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
