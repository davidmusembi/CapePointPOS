@extends('layouts.app')

@section('title', 'Sales Summary')
@section('page_subtitle', 'Invoices, sales by product and by day')

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" />
        @include('reports.partials.contact-filter', ['contacts' => $customers, 'name' => 'customer_id', 'label' => 'Customer'])
        @include('reports.partials.payment-status-filter')
    </x-filters>

    <div class="row mb-3">
        <div class="col-md col-6 mb-2"><div class="summary-box primary"><div class="label">Invoices</div><div class="value" data-summary="count" data-format="raw">0</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box accent"><div class="label">Gross sales (incl. tax)</div><div class="value" data-summary="total">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box warning"><div class="label">Tax collected</div><div class="value" data-summary="tax">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box success"><div class="label">Amount paid</div><div class="value" data-summary="paid">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box danger"><div class="label">Balance due</div><div class="value" data-summary="due">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab_invoices" role="tab"><i class="fas fa-file-invoice mr-1"></i> Invoices</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab_products" role="tab"><i class="fas fa-boxes mr-1"></i> Sales by product</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab_daily" role="tab"><i class="far fa-calendar-alt mr-1"></i> Sales by day</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_invoices" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="invoices_table">
                        <thead>
                        <tr>
                            <th>Date</th><th>Invoice No.</th><th>Customer</th><th>Subtotal</th><th>Discount</th><th>Tax</th><th>Shipping &amp; Charges</th>
                            <th>Total</th><th>Paid</th><th>Returned</th><th>Due</th><th>Status</th>
                        </tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">Total:</th>
                            <th class="text-right" data-total="subtotal"></th><th class="text-right" data-total="discount"></th>
                            <th class="text-right" data-total="tax"></th><th class="text-right" data-total="charges"></th><th class="text-right" data-total="total"></th>
                            <th class="text-right" data-total="paid"></th><th class="text-right" data-total="returned"></th>
                            <th class="text-right" data-total="due"></th><th></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="tab-pane fade" id="tab_products" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="products_table">
                        <thead>
                        <tr><th>Product</th><th>SKU</th><th>Qty Sold</th><th>Returned</th><th>Net Qty</th><th>Revenue (excl. tax)</th><th>Tax</th><th>Avg. Price</th></tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="2" class="text-right">Total:</th>
                            <th class="text-right" data-total="p_qty" data-format="qty"></th><th class="text-right" data-total="p_returned" data-format="qty"></th>
                            <th class="text-right" data-total="p_net_qty" data-format="qty"></th><th class="text-right" data-total="p_revenue"></th>
                            <th class="text-right" data-total="p_tax"></th><th></th>
                        </tr>
                        </tfoot>
                    </table>
                    <small class="text-muted">Revenue is net of line and invoice discounts, excluding tax and shipping / additional charges.</small>
                </div>
                <div class="tab-pane fade" id="tab_daily" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="daily_table">
                        <thead>
                        <tr><th>Date</th><th>Invoices</th><th>Net Sales (excl. tax)</th><th>Tax</th><th>Shipping &amp; Charges</th><th>Total</th><th>Paid</th><th>Due</th></tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th class="text-right">Total:</th>
                            <th class="text-center" data-total="d_count" data-format="raw"></th><th class="text-right" data-total="d_net"></th>
                            <th class="text-right" data-total="d_tax"></th><th class="text-right" data-total="d_charges"></th><th class="text-right" data-total="d_total"></th>
                            <th class="text-right" data-total="d_paid"></th><th class="text-right" data-total="d_due"></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('reports.partials.js')
    <script>
        $(function () {
            RPT.init();
            var url = '{{ route('reports.sales') }}';
            var r = 'text-right';

            RPT.server('#invoices_table', url, 'invoices', [
                { data: 'date', name: 'date' },
                { data: 'invoice_no', name: 'invoice_no' },
                { data: 'customer_name', name: 'customer_name', orderable: false },
                { data: 'subtotal', name: 'subtotal', render: RPT.money, className: r, searchable: false },
                { data: 'discount_amount', name: 'discount_amount', render: RPT.money, className: r, searchable: false },
                { data: 'tax_amount', name: 'tax_amount', render: RPT.money, className: r, searchable: false },
                { data: 'charges', name: 'charges', render: RPT.money, className: r, searchable: false, orderable: false },
                { data: 'total', name: 'total', render: RPT.money, className: r, searchable: false },
                { data: 'paid_amount', name: 'paid_amount', render: RPT.money, className: r, searchable: false },
                { data: 'returned_amount', name: 'returned_amount', render: RPT.money, className: r, searchable: false },
                { data: 'due_amount', name: 'due_amount', render: RPT.money, className: r, searchable: false },
                { data: 'payment_status', name: 'payment_status', searchable: false }
            ], { order: [[0, 'desc']] });

            RPT.client('#products_table', url, 'products', [
                { data: 'product' }, { data: 'sku' },
                { data: 'qty', render: RPT.qty, className: r }, { data: 'returned', render: RPT.qty, className: r },
                { data: 'net_qty', render: RPT.qty, className: r }, { data: 'revenue', render: RPT.money, className: r },
                { data: 'tax', render: RPT.money, className: r }, { data: 'avg_price', render: RPT.money, className: r }
            ], { order: [[5, 'desc']] });

            RPT.client('#daily_table', url, 'daily', [
                { data: 'date', render: RPT.sortable }, { data: 'count', className: 'text-center' },
                { data: 'net', render: RPT.money, className: r }, { data: 'tax', render: RPT.money, className: r },
                { data: 'charges', render: RPT.money, className: r },
                { data: 'total', render: RPT.money, className: r }, { data: 'paid', render: RPT.money, className: r },
                { data: 'due', render: RPT.money, className: r }
            ], { order: [[0, 'desc']] });
        });
    </script>
@endpush
