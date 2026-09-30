@extends('layouts.app')

@section('title', 'Units')
@section('page_subtitle', 'Units of measure')

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">All Units</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-sm btn-primary btn-modal" data-href="{{ route('units.create') }}"><i class="fas fa-plus"></i> Add Unit</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="units_table">
                <thead>
                <tr><th>Name</th><th>Short name</th><th>Allow decimal</th><th>Products</th><th class="no-export" style="width:90px">Action</th></tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#units_table').DataTable({
            serverSide: true,
            ajax: '{{ route('units.index') }}',
            order: [[0, 'asc']],
            columns: [
                { data: 'name', name: 'name' },
                { data: 'short_name', name: 'short_name' },
                { data: 'allow_decimal', name: 'allow_decimal', searchable: false },
                { data: 'products_count', name: 'products_count', searchable: false, className: 'text-center' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
