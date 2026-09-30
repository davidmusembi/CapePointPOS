@extends('layouts.app')

@section('title', 'Profit Snapshot')
@section('page_subtitle', 'Net sales − cost of goods sold − expenses')

@section('header_actions')
    <button type="button" class="btn btn-default no-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
@endsection

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" col="col-md-4" />
    </x-filters>

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2"><div class="summary-box accent"><div class="label">Net sales</div><div class="value" data-summary="net_sales">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box primary"><div class="label">Gross profit</div><div class="value" data-summary="gross_profit">-</div><small class="text-muted">Margin <span data-summary="gross_margin" data-format="raw"></span></small></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box danger"><div class="label">Expenses</div><div class="value" data-summary="expenses">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box success"><div class="label">Net profit</div><div class="value" id="net_profit_box" data-summary="net_profit">-</div><small class="text-muted">Margin <span data-summary="net_margin" data-format="raw"></span></small></div></div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-balance-scale mr-1 text-primary"></i> Profit &amp; loss</h3></div>
                <div class="card-body p-0">
                    <table class="table mb-0" id="pl_table">
                        <tr><td class="pl-3">Sales (excl. tax) <small class="text-muted">(<span data-summary="invoice_count" data-format="raw">0</span> invoices)</small></td><td class="text-right pr-3" data-summary="gross_sales">-</td></tr>
                        <tr><td class="pl-3">Less: invoice discounts</td><td class="text-right pr-3 text-danger" data-summary="invoice_discount">-</td></tr>
                        <tr><td class="pl-3">Less: sales returns (net of discount)</td><td class="text-right pr-3 text-danger" data-summary="returns_net">-</td></tr>
                        <tr class="table-active"><th class="pl-3">Net sales</th><th class="text-right pr-3" data-summary="net_sales">-</th></tr>
                        <tr><td class="pl-3">Less: cost of goods sold</td><td class="text-right pr-3 text-danger" data-summary="cogs">-</td></tr>
                        <tr class="table-active"><th class="pl-3">Gross profit <small class="text-muted">(<span data-summary="gross_margin" data-format="raw"></span>)</small></th><th class="text-right pr-3" data-summary="gross_profit">-</th></tr>
                        <tbody id="expense_lines"></tbody>
                        <tr><td class="pl-3">Total operating expenses</td><td class="text-right pr-3 text-danger" data-summary="expenses">-</td></tr>
                        <tr style="background:#1b4f8a;color:#fff"><th class="pl-3">Net profit <small>(<span data-summary="net_margin" data-format="raw"></span>)</small></th><th class="text-right pr-3" data-summary="net_profit">-</th></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="row">
                <div class="col-md-4 mb-3"><div class="summary-box warning"><div class="label">Output tax (collected)</div><div class="value" data-summary="output_tax">-</div></div></div>
                <div class="col-md-4 mb-3"><div class="summary-box primary"><div class="label">Input tax (on purchases)</div><div class="value" data-summary="input_tax">-</div></div></div>
                <div class="col-md-4 mb-3"><div class="summary-box danger"><div class="label">Net tax payable</div><div class="value" data-summary="net_tax">-</div></div></div>
            </div>
            <div class="card card-teal card-outline">
                <div class="card-header"><h3 class="card-title">Profit by month</h3></div>
                <div class="card-body">
                    <div class="chart-wrap" style="height:280px"><canvas id="monthly_chart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-teal card-outline">
        <div class="card-header"><h3 class="card-title">Monthly breakdown</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="monthly_table">
                <thead><tr><th>Month</th><th>Net Sales</th><th>COGS</th><th>Gross Profit</th><th>Expenses</th><th>Net Profit</th></tr></thead>
                <tfoot>
                <tr>
                    <th class="text-right">Total:</th>
                    <th class="text-right" data-total="m_net_sales"></th><th class="text-right" data-total="m_cogs"></th>
                    <th class="text-right" data-total="m_gross_profit"></th><th class="text-right" data-total="m_expenses"></th>
                    <th class="text-right" data-total="m_net_profit"></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Profit by product</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="products_table">
                <thead><tr><th>Product</th><th>SKU</th><th>Net Qty Sold</th><th>Net Revenue</th><th>COGS</th><th>Gross Profit</th><th>Margin</th></tr></thead>
                <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total:</th>
                    <th class="text-right" data-total="p_qty" data-format="qty"></th><th class="text-right" data-total="p_revenue"></th>
                    <th class="text-right" data-total="p_cogs"></th><th class="text-right" data-total="p_profit"></th><th></th>
                </tr>
                </tfoot>
            </table>
            <small class="text-muted">Invoice-level discounts are allocated to lines in proportion to line value; returns are deducted in the period they were issued.</small>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chartjs/chart.umd.js') }}"></script>
    @include('reports.partials.js')
    <script>
        $(function () {
            RPT.init();
            var url = '{{ route('reports.profit') }}', r = 'text-right', chart = null;

            RPT.fetch(url, 'summary', function (json) {
                var html = '';
                $.each(json.expenses || [], function (i, e) {
                    html += '<tr><td class="pl-4 text-muted">' + e.category + '</td><td class="text-right pr-3 text-muted">' + APP.formatMoney(e.amount) + '</td></tr>';
                });
                $('#expense_lines').html(html || '<tr><td class="pl-4 text-muted" colspan="2">No expenses in this period</td></tr>');
                var net = (json.totals || {}).net_profit || 0;
                $('[data-summary="net_profit"]').closest('.summary-box').toggleClass('success', net >= 0).toggleClass('danger', net < 0);
            });

            RPT.client('#monthly_table', url, 'monthly', [
                { data: 'month', render: RPT.sortable },
                { data: 'net_sales', render: RPT.money, className: r }, { data: 'cogs', render: RPT.money, className: r },
                { data: 'gross_profit', render: RPT.money, className: r }, { data: 'expenses', render: RPT.money, className: r },
                { data: 'net_profit', render: RPT.money, className: r + ' font-weight-bold' }
            ], {
                order: [[0, 'asc']], paging: false, info: false,
                onData: function (json) {
                    var rows = json.data || [];
                    if (chart) { chart.destroy(); }
                    chart = new Chart(document.getElementById('monthly_chart'), {
                        type: 'bar',
                        data: {
                            labels: rows.map(function (x) { return x.month.display; }),
                            datasets: [
                                { label: 'Net sales', data: rows.map(function (x) { return x.net_sales; }), backgroundColor: '#1b4f8a', borderRadius: 4 },
                                { label: 'Gross profit', data: rows.map(function (x) { return x.gross_profit; }), backgroundColor: '#0e9f8e', borderRadius: 4 },
                                { label: 'Net profit', data: rows.map(function (x) { return x.net_profit; }), backgroundColor: '#d97706', borderRadius: 4 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + APP.formatMoney(c.raw); } } } },
                            scales: { y: { ticks: { callback: function (v) { return APP.formatNumber(v, 0); } }, grid: { color: '#eef1f5' } }, x: { grid: { display: false } } }
                        }
                    });
                }
            });

            RPT.client('#products_table', url, 'products', [
                { data: 'product' }, { data: 'sku' },
                { data: 'qty', render: RPT.qty, className: r }, { data: 'revenue', render: RPT.money, className: r },
                { data: 'cogs', render: RPT.money, className: r }, { data: 'profit', render: RPT.money, className: r + ' font-weight-bold' },
                { data: 'margin', render: RPT.pct, className: r }
            ], { order: [[5, 'desc']] });
        });
    </script>
@endpush
