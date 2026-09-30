@extends('layouts.app')

@php $isCustomer = $type === 'customer'; @endphp

@section('title', $isCustomer ? 'Customer Receipts' : 'Supplier Payments')
@section('page_subtitle', $isCustomer ? 'Payments received from customers' : 'Payments made to suppliers')

@section('header_actions')
    @can('payments.create')
        <a href="{{ route('payments.create', ['type' => $type]) }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ $isCustomer ? 'Receive Payment' : 'Pay Supplier' }}</a>
    @endcan
@endsection

@section('content')
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link {{ $isCustomer ? 'active' : '' }}" href="{{ route('payments.index', ['type' => 'customer']) }}"><i class="fas fa-arrow-down mr-1"></i> Customer Receipts</a></li>
        <li class="nav-item"><a class="nav-link {{ $isCustomer ? '' : 'active' }}" href="{{ route('payments.index', ['type' => 'supplier']) }}"><i class="fas fa-arrow-up mr-1"></i> Supplier Payments</a></li>
    </ul>

    <x-filters>
        <input type="hidden" name="type" value="{{ $type }}">
        <x-date-range :start="now()->startOfMonth()->toDateString()" :end="now()->toDateString()" />
        <div class="col-md-4">
            <div class="form-group">
                <label>{{ $isCustomer ? 'Customer' : 'Supplier' }}</label>
                <select name="party_id" id="party_filter" class="form-control" data-allow-clear="true" data-placeholder="All {{ $isCustomer ? 'customers' : 'suppliers' }}"></select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Payment method</label>
                <select name="method" class="form-control custom-select">
                    <option value="">All methods</option>
                    @foreach (payment_methods(false, false) as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">{{ $isCustomer ? 'Receipts' : 'Payments' }}</h3>
            <div class="card-tools"><span class="text-muted small mr-1">Total in period:</span> <strong data-summary="amount">-</strong></div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="payments_table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Payment No</th>
                    <th>{{ $isCustomer ? 'Customer' : 'Supplier' }}</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Amount</th>
                    <th>Allocated To</th>
                    <th>Unallocated</th>
                    <th>Added By</th>
                    <th class="no-export">Action</th>
                </tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Total</th>
                    <th class="text-right" data-total="amount"></th>
                    <th colspan="4"></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var table;
        APP.contactSelect('#party_filter', '{{ route($isCustomer ? 'customers.search' : 'suppliers.search') }}').on('change', function () { table.ajax.reload(); });
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });

        table = $('#payments_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('payments.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'date' },
                { data: 'payment_no', name: 'payment_no' },
                { data: 'party', name: 'party', orderable: false },
                { data: 'method', name: 'method' },
                { data: 'reference', name: 'reference' },
                { data: 'amount', name: 'amount', className: 'text-right' },
                { data: 'allocated_to', name: 'allocated_to', orderable: false, searchable: false },
                { data: 'unallocated', name: 'unallocated', orderable: false, searchable: false, className: 'text-right' },
                { data: 'added_by', name: 'added_by', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () { APP.renderFooterTotals('#payments_table', this.api().ajax.json()); }
        });
        $('#filters_form').on('change', 'select[name=method]', function () { table.ajax.reload(); });
    });
</script>
@endpush
