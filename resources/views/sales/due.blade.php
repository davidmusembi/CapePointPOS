@extends('layouts.app')

@section('title', 'Sales Due')
@section('page_subtitle', 'Invoices with an outstanding balance')

@section('header_actions')
    @can('payments.create')
        <a href="{{ route('payments.create', ['type' => 'customer']) }}" class="btn btn-teal"><i class="fas fa-hand-holding-usd"></i> Receive Payment</a>
    @endcan
@endsection

@section('content')
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="summary-box danger">
                <div class="label">Total outstanding</div>
                <div class="value">{{ money($summary['total_due']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="summary-box warning">
                <div class="label">Overdue amount</div>
                <div class="value">{{ money($summary['overdue']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="summary-box primary">
                <div class="label">Unpaid invoices</div>
                <div class="value">{{ number_format($summary['count']) }}</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="summary-box accent">
                <div class="label">Customers owing</div>
                <div class="value">{{ number_format($summary['customers']) }}</div>
            </div>
        </div>
    </div>

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
                <label>Payment status</label>
                <select name="payment_status" class="form-control custom-select">
                    <option value="">Partial &amp; Due</option>
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
            <h3 class="card-title">Outstanding Invoices</h3>
        </div>
        <div class="card-body">
            @include('sales.partials.table', ['tableId' => 'sales_due_table', 'withOverdue' => true])
        </div>
    </div>
@endsection

@push('scripts')
    @include('sales.partials.table-script', ['tableId' => 'sales_due_table', 'url' => route('sales.due'), 'withOverdue' => true])
@endpush
