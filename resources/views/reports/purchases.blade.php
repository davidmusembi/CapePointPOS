@extends('layouts.app')

@section('title', 'Purchase Summary')
@section('page_subtitle', 'Purchase invoices and purchases by product')

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" />
        @include('reports.partials.contact-filter', ['contacts' => $suppliers, 'name' => 'supplier_id', 'label' => 'Supplier'])
        @include('reports.partials.payment-status-filter')
    </x-filters>

    <div class="row mb-3">
        <div class="col-md col-6 mb-2"><div class="summary-box primary"><div class="label">Purchases</div><div class="value" data-summary="count" data-format="raw">0</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box accent"><div class="label">Total purchases (incl. tax)</div><div class="value" data-summary="total">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box warning"><div class="label">Input tax</div><div class="value" data-summary="tax">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box success"><div class="label">Amount paid</div><div class="value" data-summary="paid">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box danger"><div class="label">Balance due</div><div class="value" data-summary="due">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab_purchases" role="tab"><i class="fas fa-truck-loading mr-1"></i> Purchases</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab_products" role="tab"><i class="fas fa-boxes mr-1"></i> Purchases by product</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_purchases" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="purchases_table">
                        <thead>
                        <tr>
                            <th>Date</th><th>Purchase No.</th><th>Supplier Inv.</th><th>Supplier</th><th>Subtotal</th><th>Discount</th><th>Tax</th><th>Shipping &amp; Charges</th>
                            <th>Total</th><th>Paid</th><th>Returned</th><th>Due</th><th>Status</th>
                        </tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="4" class="text-right">Total:</th>
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
                        <tr><th>Product</th><th>SKU</th><th>Qty Purchased</th><th>Returned</th><th>Net Qty</th><th>Amount (excl. tax)</th><th>Tax</th><th>Avg. Cost</th></tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="2" class="text-right">Total:</th>
                            <th class="text-right" data-total="p_qty" data-format="qty"></th><th class="text-right" data-total="p_returned" data-format="qty"></th>
                            <th class="text-right" data-total="p_net_qty" data-format="qty"></th><th class="text-right" data-total="p_amount"></th>
                            <th class="text-right" data-total="p_tax"></th><th></th>
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
            var url = '{{ route('reports.purchases') }}', r = 'text-right';

            RPT.server('#purchases_table', url, 'purchases', [
                { data: 'date', name: 'date' },
                { data: 'purchase_no', name: 'purchase_no' },
                { data: 'supplier_invoice_no', name: 'supplier_invoice_no' },
                { data: 'supplier_name', name: 'supplier_name', orderable: false },
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
                { data: 'net_qty', render: RPT.qty, className: r }, { data: 'amount', render: RPT.money, className: r },
                { data: 'tax', render: RPT.money, className: r }, { data: 'avg_cost', render: RPT.money, className: r }
            ], { order: [[5, 'desc']] });
        });
    </script>
@endpush
