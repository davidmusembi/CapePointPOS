@extends('layouts.app')

@section('title', 'Sales Returns')
@section('page_subtitle', 'Credit notes issued to customers')

@section('header_actions')
    @can('sale_returns.create')
        <a href="{{ route('sale-returns.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Sales Return</a>
    @endcan
@endsection

@section('content')
    <x-filters>
        <x-date-range start="2000-01-01" :end="now()->toDateString()" />
        <div class="col-md-4">
            <div class="form-group">
                <label>Customer</label>
                <select name="customer_id" id="filter_customer" class="form-control" data-allow-clear="true" data-placeholder="All customers"></select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All Sales Returns</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="returns_table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Credit Note No</th>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Items Qty</th>
                    <th>Total</th>
                    <th>Refunded</th>
                    <th>Reason</th>
                    <th class="no-export" style="width:90px">Action</th>
                </tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Total:</th>
                    <th class="text-right" data-total="total"></th>
                    <th class="text-right" data-total="refund"></th>
                    <th></th>
                    <th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        APP.contactSelect('#filter_customer', '{{ route('customers.search') }}');
        var table = $('#returns_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('sale-returns.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'sale_returns.date' },
                { data: 'return_no', name: 'sale_returns.return_no' },
                { data: 'invoice_no', name: 'invoice_no', orderable: false },
                { data: 'customer_name', name: 'customer_name', orderable: false },
                { data: 'items_qty', name: 'items_qty', className: 'text-right', searchable: false },
                { data: 'total', name: 'sale_returns.total', className: 'text-right', searchable: false },
                { data: 'refund_amount', name: 'sale_returns.refund_amount', className: 'text-right', searchable: false },
                { data: 'reason', name: 'sale_returns.reason' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () { APP.renderFooterTotals('#returns_table', this.api().ajax.json()); }
        });
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
