@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_subtitle', 'Welcome back, '.auth()->user()->name)

@section('header_actions')
    <button type="button" class="btn btn-primary" id="dashboard_date_filter">
        <i class="far fa-calendar-alt mr-1"></i> <span>Filter by date</span> <i class="fas fa-caret-down ml-1"></i>
    </button>
    @can('reports.sales')
        <a href="{{ route('reports.purchase-sale') }}" class="btn btn-default"><i class="fas fa-chart-pie"></i> Reports</a>
    @endcan
@endsection

@section('content')
    {{-- Date-filtered figures (Filter by date) --}}
    <div class="text-muted small mb-2"><i class="far fa-calendar-check mr-1"></i> Showing: <strong id="range_label" class="text-dark">Today</strong></div>
    @php
        $tiles = [
            ['total_sales', 'Total sales', 'fas fa-file-invoice-dollar', 'tone-teal', 'invoice_count', 'invoices', 'sales.view', 'sales.index'],
            ['net_sales', 'Net sales (excl. tax)', 'fas fa-balance-scale', 'tone-blue', null, 'After discounts & returns', 'sales.view', null],
            ['invoice_due', 'Invoice due', 'fas fa-hourglass-half', 'tone-amber', null, 'Unpaid on these invoices', 'sales.view', 'sales.due'],
            ['sales_returns', 'Sales returns', 'fas fa-undo-alt', 'tone-red', 'returns_count', 'credit notes', 'sale_returns.view', 'sale-returns.index'],
            ['total_purchases', 'Total purchases', 'fas fa-truck-loading', 'tone-violet', 'purchase_count', 'purchases', 'purchases.view', 'purchases.index'],
            ['purchase_due', 'Purchase due', 'fas fa-file-invoice', 'tone-amber', null, 'Unpaid on these purchases', 'purchases.view', null],
            ['purchase_returns', 'Purchase returns', 'fas fa-reply-all', 'tone-red', null, 'Debit notes', 'purchase_returns.view', 'purchase-returns.index'],
            ['expenses', 'Expenses', 'fas fa-wallet', 'tone-red', null, 'Recorded expenses', 'expenses.view', 'expenses.index'],
            ['gross_profit', 'Gross profit', 'fas fa-chart-line', 'tone-green', null, 'Net sales − cost of goods', 'reports.profit', 'reports.profit'],
            ['net_profit', 'Net profit', 'fas fa-coins', 'tone-green', null, 'Gross profit − expenses', 'reports.profit', 'reports.profit'],
            ['received', 'Payments received', 'fas fa-hand-holding-usd', 'tone-teal', null, 'From customers', 'payments.view', null],
            ['paid_out', 'Payments made', 'fas fa-money-check-alt', 'tone-blue', null, 'To suppliers', 'payments.view', null],
        ];
    @endphp
    <div class="row" id="dashboard_tiles">
        @foreach ($tiles as [$key, $label, $icon, $tone, $countKey, $sub, $perm, $link])
            @can($perm)
                <div class="col-xl-3 col-md-6">
                    <div class="stat-tile {{ $tone }} position-relative">
                        <div class="stat-icon"><i class="{{ $icon }}"></i></div>
                        <div>
                            <div class="stat-label">{{ $label }}</div>
                            <div class="stat-value" data-metric="{{ $key }}"><span class="text-muted">…</span></div>
                            <div class="stat-sub">@if ($countKey)<span data-metric-count="{{ $countKey }}">0</span> @endif{{ $sub }}</div>
                        </div>
                        @if ($link)<a href="{{ route($link) }}" class="stretched-link" aria-label="{{ $label }}"></a>@endif
                    </div>
                </div>
            @endcan
        @endforeach
    </div>

    {{-- Current balances (all dates) --}}
    <div class="form-section-title mt-1">Current position (all dates)</div>
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="summary-box warning mb-3">
                <div class="label">Receivables</div>
                <div class="value">{{ money($snapshot['receivables']) }}</div>
                <small class="text-danger"><i class="fas fa-exclamation-circle"></i> Overdue {{ money($snapshot['overdue_receivables']) }}</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="summary-box primary mb-3">
                <div class="label">Payables</div>
                <div class="value">{{ money($snapshot['payables']) }}</div>
                <small class="text-muted">Overdue {{ money($snapshot['overdue_payables']) }}</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="summary-box danger mb-3">
                <div class="label">Low stock items</div>
                <div class="value">{{ $snapshot['low_stock'] }}</div>
                <small class="text-muted">{{ $snapshot['out_of_stock'] }} out of stock</small>
            </div>
        </div>
    </div>

    {{-- Chart --}}
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">Sales vs purchases</h3>
            <div class="card-tools">
                <div class="btn-group btn-group-sm" id="chart_range">
                    <button type="button" class="btn btn-default" data-days="7">7 days</button>
                    <button type="button" class="btn btn-default active" data-days="30">30 days</button>
                    <button type="button" class="btn btn-default" data-days="90">90 days</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap mb-2 small" style="gap:1.25rem">
                <span><span style="display:inline-block;width:14px;height:3px;background:#2f6db3;vertical-align:middle;border-radius:2px"></span> Sales <strong id="chart_total_sales" class="ml-1"></strong></span>
                <span><span style="display:inline-block;width:14px;height:3px;background:#0e9f8e;vertical-align:middle;border-radius:2px"></span> Purchases <strong id="chart_total_purchases" class="ml-1"></strong></span>
            </div>
            <div class="chart-wrap"><canvas id="sales_chart" aria-label="Daily sales and purchases" role="img"></canvas></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent invoices</h3>
                    @can('sales.view')<div class="card-tools"><a href="{{ route('sales.index') }}" class="btn btn-tool">View all</a></div>@endcan
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th class="pl-3">Invoice</th><th>Date</th><th>Customer</th><th class="text-right">Total</th><th class="text-right">Due</th><th class="pr-3">Status</th></tr></thead>
                        <tbody>
                        @forelse ($recentSales as $s)
                            <tr>
                                <td class="pl-3"><a href="{{ route('sales.show', $s) }}">{{ $s->invoice_no }}</a></td>
                                <td>{{ format_date($s->date) }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($s->customer->display_name ?? '-', 28) }}</td>
                                <td class="text-right">{{ money($s->total) }}</td>
                                <td class="text-right">{{ money($s->due_amount) }}</td>
                                <td class="pr-3">{!! payment_status_badge($s->payment_status) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">No invoices yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-clock text-danger mr-1"></i> Overdue invoices</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th class="pl-3">Invoice</th><th>Customer</th><th>Due date</th><th>Days overdue</th><th class="text-right pr-3">Due</th></tr></thead>
                        <tbody>
                        @forelse ($overdueInvoices as $s)
                            <tr>
                                <td class="pl-3"><a href="{{ route('sales.show', $s) }}">{{ $s->invoice_no }}</a></td>
                                <td>{{ \Illuminate\Support\Str::limit($s->customer->display_name ?? '-', 28) }}</td>
                                <td>{{ format_date($s->due_date) }}</td>
                                <td><span class="badge badge-danger">{{ (int) $s->due_date->diffInDays(now()) }} days</span></td>
                                <td class="text-right pr-3 font-weight-600">{{ money($s->due_amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state"><i class="fas fa-check-circle text-success d-block"></i>No overdue invoices.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-exclamation-triangle text-warning mr-1"></i> Low stock</h3>
                    @can('products.view')<div class="card-tools"><a href="{{ route('products.low-stock') }}" class="btn btn-tool">View all</a></div>@endcan
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th class="pl-3">Product</th><th class="text-center">Stock</th><th class="text-center pr-3">Alert at</th></tr></thead>
                        <tbody>
                        @forelse ($lowStock as $p)
                            <tr>
                                <td class="pl-3"><a href="{{ route('products.show', $p) }}">{{ \Illuminate\Support\Str::limit($p->name, 34) }}</a></td>
                                <td class="text-center"><span class="badge {{ $p->stock_quantity <= 0 ? 'badge-danger' : 'badge-warning' }}">{{ qty_format($p->stock_quantity) }} {{ $p->unit->short_name ?? '' }}</span></td>
                                <td class="text-center pr-3">{{ qty_format($p->alert_quantity) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">All products are above alert levels.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Top customers by balance</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                        @forelse ($topDebtors as $c)
                            <tr>
                                <td class="pl-3"><a href="{{ route('customers.show', $c) }}">{{ \Illuminate\Support\Str::limit($c->display_name, 36) }}</a></td>
                                <td class="text-right pr-3 amount-positive">{{ money($c->balance) }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty-state">No outstanding balances.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Top products (30 days)</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th class="pl-3">Product</th><th class="text-right">Qty</th><th class="text-right pr-3">Revenue</th></tr></thead>
                        <tbody>
                        @forelse ($topProducts as $p)
                            <tr>
                                <td class="pl-3">{{ \Illuminate\Support\Str::limit($p->name, 32) }}</td>
                                <td class="text-right">{{ qty_format($p->qty) }}</td>
                                <td class="text-right pr-3">{{ money($p->revenue) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">No sales in the last 30 days.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
<script>
    $(function () {
        // ---- Date filter (UltimatePOS style) ----
        var rangeKey = 'dashboard_range';
        var fmt = APP.momentDateFormat || 'DD/MM/YYYY';
        function loadSummary(start, end, label) {
            $('#range_label').text(label ? label + ' (' + start.format(fmt) + ' – ' + end.format(fmt) + ')' : start.format(fmt) + ' – ' + end.format(fmt));
            $('#dashboard_date_filter span').text(label || 'Custom range');
            $('[data-metric]').html('<i class="fas fa-circle-notch fa-spin text-muted" style="font-size:1rem"></i>');
            $.getJSON('{{ route('dashboard.summary') }}', { start_date: start.format('YYYY-MM-DD'), end_date: end.format('YYYY-MM-DD') }, function (res) {
                $.each(res.metrics, function (k, v) {
                    $('[data-metric="' + k + '"]').text(APP.formatMoney(v)).toggleClass('text-danger', (k === 'net_profit' || k === 'gross_profit') && v < 0);
                    $('[data-metric-count="' + k + '"]').text(v);
                });
            }).fail(function (xhr) { APP.handleError(xhr); });
            try { localStorage.setItem(rangeKey, JSON.stringify({ label: label, start: start.format('YYYY-MM-DD'), end: end.format('YYYY-MM-DD') })); } catch (e) {}
        }

        var ranges = APP.dateRanges();
        var initial = { label: 'Today', start: moment(), end: moment() };
        try {
            var saved = JSON.parse(localStorage.getItem(rangeKey) || 'null');
            if (saved && saved.label && ranges[saved.label]) {
                initial = { label: saved.label, start: ranges[saved.label][0], end: ranges[saved.label][1] };
            } else if (saved && saved.start) {
                initial = { label: null, start: moment(saved.start), end: moment(saved.end) };
            }
        } catch (e) {}

        $('#dashboard_date_filter').daterangepicker({
            startDate: initial.start, endDate: initial.end, ranges: ranges, opens: 'left',
            alwaysShowCalendars: false, showCustomRangeLabel: true,
            locale: { format: fmt, customRangeLabel: 'Custom Range' }
        }, function (start, end, label) { loadSummary(start, end, label === 'Custom Range' ? null : label); });
        loadSummary(initial.start, initial.end, initial.label);

        var chart;
        var colors = { sales: '#2f6db3', purchases: '#0e9f8e' };

        function load(days) {
            $.getJSON('{{ route('dashboard.chart') }}', { days: days }, function (res) {
                $('#chart_total_sales').text(APP.formatMoney(res.totals.sales));
                $('#chart_total_purchases').text(APP.formatMoney(res.totals.purchases));
                var data = {
                    labels: res.labels,
                    datasets: [
                        { label: 'Sales', data: res.sales, borderColor: colors.sales, backgroundColor: colors.sales, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderWidth: 2, pointHoverBorderColor: '#fff', tension: 0.25 },
                        { label: 'Purchases', data: res.purchases, borderColor: colors.purchases, backgroundColor: colors.purchases, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHoverBorderWidth: 2, pointHoverBorderColor: '#fff', tension: 0.25 }
                    ]
                };
                if (chart) { chart.data = data; chart.update(); return; }
                chart = new Chart(document.getElementById('sales_chart'), {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false }, // custom legend above the chart (with totals)
                            tooltip: {
                                backgroundColor: '#0f1e36', padding: 10, boxPadding: 4, usePointStyle: true,
                                callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + APP.formatMoney(c.parsed.y); } }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: '#6b7686', maxRotation: 0, autoSkipPadding: 16 }, border: { color: '#e3e8ef' } },
                            y: { beginAtZero: true, grid: { color: '#eef1f5' }, border: { display: false },
                                ticks: { color: '#6b7686', callback: function (v) { return v >= 1e6 ? (v / 1e6) + 'M' : (v >= 1e3 ? (v / 1e3) + 'K' : v); } } }
                        }
                    }
                });
            });
        }

        $('#chart_range').on('click', 'button', function () {
            $(this).addClass('active').siblings().removeClass('active');
            load($(this).data('days'));
        });
        load(30);
    });
</script>
@endpush
