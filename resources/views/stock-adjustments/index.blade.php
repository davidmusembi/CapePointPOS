@extends('layouts.app')

@section('title', 'Stock Adjustments')
@section('page_subtitle', 'Damages, losses, count corrections')

@section('header_actions')
    @can('stock_adjustments.create')
        <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Adjustment</a>
    @endcan
@endsection

@section('content')
    <x-filters>
        <x-date-range :start="now()->startOfYear()->toDateString()" :end="now()->toDateString()" />
        <div class="col-md-3">
            <div class="form-group">
                <label>Type</label>
                <select name="type" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="increase">Increase</option>
                    <option value="decrease">Decrease</option>
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All adjustments</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="adjustments_table">
                <thead>
                <tr><th>Date</th><th>Reference</th><th>Type</th><th>Reason</th><th>Items</th><th>Value</th><th>Added By</th><th class="no-export">Action</th></tr>
                </thead>
                <tfoot>
                <tr><th colspan="5" class="text-right">Total</th><th class="text-right" data-total="total_amount"></th><th colspan="2"></th></tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var table;
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        table = $('#adjustments_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('stock-adjustments.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'date' },
                { data: 'reference_no', name: 'reference_no' },
                { data: 'type', name: 'type' },
                { data: 'reason', name: 'reason' },
                { data: 'items_count', name: 'items_count', searchable: false, className: 'text-center' },
                { data: 'total_amount', name: 'total_amount', className: 'text-right' },
                { data: 'added_by', name: 'added_by', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () { APP.renderFooterTotals('#adjustments_table', this.api().ajax.json()); }
        });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
