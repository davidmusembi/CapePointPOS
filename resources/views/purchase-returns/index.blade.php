@extends('layouts.app')

@section('title', 'Purchase Returns')
@section('page_subtitle', 'Goods returned to suppliers (debit notes)')

@section('header_actions')
    @can('purchase_returns.create')
        <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Purchase Return</a>
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
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All Purchase Returns</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="purchase_returns_table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Return No</th>
                    <th>Purchase No</th>
                    <th>Supplier</th>
                    <th>Total</th>
                    <th>Refund</th>
                    <th>Reason</th>
                    <th class="no-export">Action</th>
                </tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="4" class="text-right">Total:</th>
                    <th class="text-right" data-total="total"></th>
                    <th class="text-right" data-total="refund"></th>
                    <th colspan="2"></th>
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
        var table = $('#purchase_returns_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('purchase-returns.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'date', searchable: false },
                { data: 'return_no', name: 'return_no' },
                { data: 'purchase_no', name: 'purchase_no', orderable: false },
                { data: 'supplier_name', name: 'supplier_name', orderable: false },
                { data: 'total', name: 'total', className: 'text-right', searchable: false },
                { data: 'refund_amount', name: 'refund_amount', className: 'text-right', searchable: false },
                { data: 'reason', name: 'reason' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () { APP.renderFooterTotals('#purchase_returns_table', this.api().ajax.json()); }
        });
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
