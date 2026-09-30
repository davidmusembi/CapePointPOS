@extends('layouts.app')

@section('title', 'Delivery Notes')
@section('page_subtitle', 'Goods dispatched to customers')

@section('header_actions')
    @can('delivery_notes.create')
        <a href="{{ route('delivery-notes.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Delivery Note</a>
    @endcan
@endsection

@section('content')
    <x-filters>
        <x-date-range start="2000-01-01" :end="now()->toDateString()" />
        <div class="col-md-3">
            <div class="form-group">
                <label>Customer</label>
                <select name="customer_id" id="filter_customer" class="form-control" data-allow-clear="true" data-placeholder="All customers"></select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control custom-select">
                    <option value="">All</option>
                    @foreach (\App\Models\DeliveryNote::STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All Delivery Notes</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="dn_table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>DN No</th>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Delivery Address</th>
                    <th>Driver / Vehicle</th>
                    <th>Status</th>
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
        APP.contactSelect('#filter_customer', '{{ route('customers.search') }}');
        var table = $('#dn_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('delivery-notes.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'delivery_notes.date' },
                { data: 'delivery_no', name: 'delivery_notes.delivery_no' },
                { data: 'invoice_no', name: 'invoice_no', orderable: false },
                { data: 'customer_name', name: 'customer_name', orderable: false },
                { data: 'delivery_address', name: 'delivery_notes.delivery_address' },
                { data: 'transport', name: 'delivery_notes.driver_name' },
                { data: 'status', name: 'delivery_notes.status', className: 'text-center' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
