@extends('layouts.app')

@section('title', 'Tax Rates')
@section('page_subtitle', 'Taxes applied to products and documents')

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">All Tax Rates</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-sm btn-primary btn-modal" data-href="{{ route('tax-rates.create') }}"><i class="fas fa-plus"></i> Add Tax Rate</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="tax_rates_table">
                <thead>
                <tr><th>Name</th><th>Rate</th><th>Products</th><th></th><th class="no-export" style="width:90px">Action</th></tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#tax_rates_table').DataTable({
            serverSide: true,
            ajax: '{{ route('tax-rates.index') }}',
            order: [[0, 'asc']],
            columns: [
                { data: 'name', name: 'name' },
                { data: 'rate', name: 'rate' },
                { data: 'products_count', name: 'products_count', searchable: false, className: 'text-center' },
                { data: 'is_default', name: 'is_default', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
