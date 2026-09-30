@extends('layouts.app')

@section('title', 'Aging Report')
@section('page_subtitle', 'Sales due grouped by age')

@section('content')
    <x-filters>
        <div class="col-md-3">
            <div class="form-group">
                <label for="as_of">As of date</label>
                <input type="date" id="as_of" name="as_of" class="form-control" value="{{ $asOf }}">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label for="age_by">Age by</label>
                <select id="age_by" name="age_by" class="form-control custom-select">
                    <option value="invoice" @selected($ageBy === 'invoice')>Invoice date</option>
                    <option value="due" @selected($ageBy === 'due')>Due date</option>
                </select>
            </div>
        </div>
        @include('reports.partials.contact-filter', ['contacts' => $customers, 'name' => 'customer_id', 'label' => 'Customer'])
    </x-filters>

    <div class="row mb-3">
        <div class="col-md col-6 mb-2 bucket-current"><div class="summary-box success"><div class="label">{{ $labels['current'] }} (not yet due)</div><div class="value" data-summary="current">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box accent"><div class="label">{{ $labels['b30'] }}</div><div class="value" data-summary="b30">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box primary"><div class="label">{{ $labels['b60'] }}</div><div class="value" data-summary="b60">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box warning"><div class="label">{{ $labels['b90'] }}</div><div class="value" data-summary="b90">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box danger"><div class="label">{{ $labels['b90p'] }}</div><div class="value" data-summary="b90p">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box danger"><div class="label">Total outstanding</div><div class="value" data-summary="total">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Aging by customer</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="aging_table">
                <thead>
                <tr>
                    <th>Customer</th><th>Code</th><th>Invoices</th><th>{{ $labels['current'] }}</th><th>{{ $labels['b30'] }}</th>
                    <th>{{ $labels['b60'] }}</th><th>{{ $labels['b90'] }}</th><th>{{ $labels['b90p'] }}</th><th>Total Due</th>
                </tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total:</th>
                    <th class="text-center" data-total="invoices" data-format="raw"></th>
                    <th class="text-right" data-total="current"></th><th class="text-right" data-total="b30"></th>
                    <th class="text-right" data-total="b60"></th><th class="text-right" data-total="b90"></th>
                    <th class="text-right" data-total="b90p"></th><th class="text-right" data-total="total"></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card card-teal card-outline">
        <div class="card-header"><h3 class="card-title">Invoice detail</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="aging_invoices_table">
                <thead>
                <tr><th>Invoice No.</th><th>Customer</th><th>Date</th><th>Due Date</th><th>Age (days)</th><th>Bucket</th><th>Total</th><th>Paid</th><th>Due</th></tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="6" class="text-right">Total:</th>
                    <th class="text-right" data-total="i_total"></th><th class="text-right" data-total="i_paid"></th><th class="text-right" data-total="i_due"></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    @include('reports.partials.js')
    <script>
        $(function () {
            RPT.init();
            var url = '{{ route('reports.aging') }}', r = 'text-right';

            var aging = RPT.client('#aging_table', url, 'summary', [
                { data: 'customer' }, { data: 'code' }, { data: 'invoices', className: 'text-center' },
                { data: 'current', render: RPT.money, className: r }, { data: 'b30', render: RPT.money, className: r },
                { data: 'b60', render: RPT.money, className: r }, { data: 'b90', render: RPT.money, className: r },
                { data: 'b90p', render: RPT.money, className: r }, { data: 'total', render: RPT.money, className: r + ' font-weight-bold' }
            ], { order: [[8, 'desc']] });

            RPT.client('#aging_invoices_table', url, 'invoices', [
                { data: 'invoice' }, { data: 'customer' },
                { data: 'date', render: RPT.sortable }, { data: 'due_date', render: RPT.sortable },
                { data: 'days', className: 'text-center' }, { data: 'bucket' },
                { data: 'total', render: RPT.money, className: r }, { data: 'paid', render: RPT.money, className: r },
                { data: 'due', render: RPT.money, className: r }
            ], { order: [[4, 'desc']] });

            function toggleCurrent() {
                var byDue = $('#age_by').val() === 'due';
                aging.column(3).visible(byDue);
                $('.bucket-current').toggle(byDue);
            }
            $('#age_by').on('change', toggleCurrent);
            toggleCurrent();
        });
    </script>
@endpush
