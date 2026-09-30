@extends('layouts.app')

@section('title', 'Expense Report')
@section('page_subtitle', 'Expenses by category, payment method and detail')

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" />
        <div class="col-md-3">
            <div class="form-group">
                <label>Expense category</label>
                <select name="expense_category_id" class="form-control select2" data-allow-clear="true" data-placeholder="All categories">
                    <option value=""></option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Payment method</label>
                <select name="payment_method" class="form-control custom-select">
                    <option value="">All</option>
                    @foreach (payment_methods(false, false) as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filters>

    <div class="row mb-3">
        <div class="col-md-4 mb-2"><div class="summary-box danger"><div class="label">Total expenses</div><div class="value" data-summary="c_amount">-</div></div></div>
        <div class="col-md-4 mb-2"><div class="summary-box primary"><div class="label">Number of expenses</div><div class="value" data-summary="c_count" data-format="raw">0</div></div></div>
        <div class="col-md-4 mb-2"><div class="summary-box accent"><div class="label">Categories used</div><div class="value" data-summary="c_categories" data-format="raw">0</div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">By category</h3></div>
                <div class="card-body">
                    <div class="chart-wrap mb-3" style="height:260px"><canvas id="category_chart"></canvas></div>
                    <table class="table table-bordered table-striped table-sm w-100" id="categories_table">
                        <thead><tr><th>Category</th><th>Count</th><th>Amount</th><th>% of Total</th></tr></thead>
                        <tfoot>
                        <tr><th class="text-right">Total:</th><th class="text-center" data-total="c_count" data-format="raw"></th><th class="text-right" data-total="c_amount"></th><th></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-teal card-outline">
                <div class="card-header"><h3 class="card-title">By payment method</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped table-sm w-100" id="methods_table">
                        <thead><tr><th>Method</th><th>Count</th><th>Amount</th><th>%</th></tr></thead>
                        <tfoot>
                        <tr><th class="text-right">Total:</th><th class="text-center" data-total="m_count" data-format="raw"></th><th class="text-right" data-total="m_amount"></th><th></th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Expense detail</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="expenses_table">
                <thead><tr><th>Date</th><th>Reference</th><th>Category</th><th>Payee</th><th>Method</th><th>Payment Ref.</th><th>Amount</th></tr></thead>
                <tfoot>
                <tr><th colspan="6" class="text-right">Total:</th><th class="text-right" data-total="amount"></th></tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
    @include('reports.partials.js')
    <script>
        $(function () {
            RPT.init();
            var url = '{{ route('reports.expenses') }}', r = 'text-right', chart = null;

            function drawChart(rows) {
                var labels = rows.map(function (x) { return $('<div>').html(x.category).text(); });
                var values = rows.map(function (x) { return x.amount; });
                if (chart) { chart.destroy(); }
                chart = new Chart(document.getElementById('category_chart'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Amount', data: values, backgroundColor: '#0e9f8e', borderRadius: 6, maxBarThickness: 22 }] },
                    options: {
                        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return APP.formatMoney(c.raw); } } } },
                        scales: { x: { ticks: { callback: function (v) { return APP.formatNumber(v, 0); } }, grid: { color: '#eef1f5' } }, y: { grid: { display: false } } }
                    }
                });
            }

            RPT.client('#categories_table', url, 'categories', [
                { data: 'category' }, { data: 'count', className: 'text-center' },
                { data: 'amount', render: RPT.money, className: r }, { data: 'percent', render: RPT.pct, className: r }
            ], { order: [[2, 'desc']], paging: false, info: false, onData: function (json) { drawChart(json.data || []); } });

            RPT.client('#methods_table', url, 'methods', [
                { data: 'method' }, { data: 'count', className: 'text-center' },
                { data: 'amount', render: RPT.money, className: r }, { data: 'percent', render: RPT.pct, className: r }
            ], { order: [[2, 'desc']], paging: false, info: false, searching: false });

            RPT.server('#expenses_table', url, 'list', [
                { data: 'date', name: 'date' },
                { data: 'reference_no', name: 'reference_no' },
                { data: 'category_name', name: 'category_name', orderable: false },
                { data: 'payee', name: 'payee' },
                { data: 'payment_method', name: 'payment_method' },
                { data: 'payment_reference', name: 'payment_reference' },
                { data: 'amount', name: 'amount', render: RPT.money, className: r, searchable: false }
            ], { order: [[0, 'desc']] });
        });
    </script>
@endpush
