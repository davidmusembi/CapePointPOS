@extends('layouts.app')

@section('title', 'Purchases')
@section('page_subtitle', 'Purchase invoices & goods received')

@section('header_actions')
    @can('purchases.create')
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Purchase</a>
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
                <label>Payment status</label>
                <select name="payment_status" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partial</option>
                    <option value="due">Due</option>
                </select>
            </div>
        </div>
    </x-filters>

    <div class="row">
        <div class="col-md-3 col-6 mb-3"><div class="summary-box primary"><div class="label">Total purchases</div><div class="value" data-summary="total">-</div></div></div>
        <div class="col-md-3 col-6 mb-3"><div class="summary-box success"><div class="label">Paid</div><div class="value" data-summary="paid">-</div></div></div>
        <div class="col-md-3 col-6 mb-3"><div class="summary-box warning"><div class="label">Returned</div><div class="value" data-summary="returned">-</div></div></div>
        <div class="col-md-3 col-6 mb-3"><div class="summary-box danger"><div class="label">Balance due</div><div class="value" data-summary="due">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All Purchases</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="purchases_table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Purchase No</th>
                    <th>Supplier Inv.</th>
                    <th>LPO</th>
                    <th>Supplier</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Returned</th>
                    <th>Due</th>
                    <th>Payment</th>
                    <th>Added By</th>
                    <th class="no-export">Action</th>
                </tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Total:</th>
                    <th class="text-right" data-total="total"></th>
                    <th class="text-right" data-total="paid"></th>
                    <th class="text-right" data-total="returned"></th>
                    <th class="text-right" data-total="due"></th>
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
        var table = $('#purchases_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('purchases.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'date', searchable: false },
                { data: 'purchase_no', name: 'purchase_no' },
                { data: 'supplier_invoice_no', name: 'supplier_invoice_no' },
                { data: 'lpo_no', name: 'lpo_no', orderable: false },
                { data: 'supplier_name', name: 'supplier_name', orderable: false },
                { data: 'total', name: 'total', className: 'text-right', searchable: false },
                { data: 'paid_amount', name: 'paid_amount', className: 'text-right', searchable: false },
                { data: 'returned_amount', name: 'returned_amount', className: 'text-right', searchable: false },
                { data: 'due_amount', name: 'due_amount', className: 'text-right', searchable: false },
                { data: 'payment_status', name: 'payment_status' },
                { data: 'added_by', name: 'added_by', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () { APP.renderFooterTotals('#purchases_table', this.api().ajax.json()); }
        });
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
