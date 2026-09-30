@extends('layouts.app')

@section('title', 'Purchase Orders (LPO)')
@section('page_subtitle', 'Local purchase orders sent to suppliers')

@section('header_actions')
    @can('purchase_orders.create')
        <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New LPO</a>
    @endcan
@endsection

@section('content')
    <x-filters>
        <x-date-range :start="now()->startOfYear()->toDateString()" :end="now()->toDateString()" />
        <div class="col-md-4">
            <div class="form-group">
                <label>Supplier</label>
                <select name="supplier_id" id="filter_supplier" class="form-control" data-allow-clear="true" data-placeholder="All suppliers"></select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="partial">Partially received</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All LPOs</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="lpo_table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>LPO No</th>
                    <th>Supplier</th>
                    <th>Expected</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Added By</th>
                    <th class="no-export">Action</th>
                </tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Total:</th>
                    <th class="text-right" data-total="total"></th>
                    <th colspan="3"></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        APP.contactSelect('#filter_supplier', '{{ route('suppliers.search') }}');
        var table = $('#lpo_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('purchase-orders.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'date', searchable: false },
                { data: 'lpo_no', name: 'lpo_no' },
                { data: 'supplier_name', name: 'supplier_name', orderable: false },
                { data: 'expected_date', name: 'expected_date', searchable: false },
                { data: 'items_count', name: 'items_count', className: 'text-center', searchable: false },
                { data: 'total', name: 'total', className: 'text-right', searchable: false },
                { data: 'status', name: 'status' },
                { data: 'added_by', name: 'added_by', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () { APP.renderFooterTotals('#lpo_table', this.api().ajax.json()); }
        });
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
