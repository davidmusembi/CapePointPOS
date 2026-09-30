@extends('layouts.app')

@section('title', 'Sales Invoices')
@section('page_subtitle', 'All sales invoices')

@section('header_actions')
    @can('sales.create')
        <a href="{{ route('sales.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Create Invoice</a>
    @endcan
@endsection

@section('content')
    <x-filters>
        <x-date-range :start="now()->startOfMonth()->toDateString()" :end="now()->toDateString()" />
        <div class="col-md-3">
            <div class="form-group">
                <label>Customer</label>
                <select name="customer_id" id="filter_customer" class="form-control" data-allow-clear="true" data-placeholder="All customers"></select>
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
        <div class="col-md-3">
            <div class="form-group">
                <label class="d-block">&nbsp;</label>
                <div class="custom-control custom-checkbox mt-2">
                    <input type="checkbox" class="custom-control-input" id="filter_overdue" name="overdue" value="1">
                    <label class="custom-control-label font-weight-normal" for="filter_overdue">Overdue only</label>
                </div>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">All Invoices</h3>
        </div>
        <div class="card-body">
            @include('sales.partials.table', ['tableId' => 'sales_table', 'withOverdue' => false])
        </div>
    </div>
@endsection

@push('scripts')
    @include('sales.partials.table-script', ['tableId' => 'sales_table', 'url' => route('sales.index'), 'withOverdue' => false])
@endpush
