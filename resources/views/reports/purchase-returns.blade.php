@extends('layouts.app')

@section('title', 'Purchase Returns Report')
@section('page_subtitle', 'Debit notes raised against suppliers')

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" />
        @include('reports.partials.contact-filter', ['contacts' => $suppliers, 'name' => 'supplier_id', 'label' => 'Supplier'])
    </x-filters>

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2"><div class="summary-box primary"><div class="label">Debit notes</div><div class="value" data-summary="count" data-format="raw">0</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box accent"><div class="label">Returns (excl. tax)</div><div class="value" data-summary="subtotal">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box danger"><div class="label">Total returned</div><div class="value" data-summary="total">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box success"><div class="label">Refunds received</div><div class="value" data-summary="refund">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Debit notes</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="returns_table">
                <thead>
                <tr><th>Date</th><th>Debit Note</th><th>Purchase</th><th>Supplier</th><th>Subtotal</th><th>Tax</th><th>Discount</th><th>Total</th><th>Refunded</th><th>Reason</th></tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="4" class="text-right">Total:</th>
                    <th class="text-right" data-total="subtotal"></th><th class="text-right" data-total="tax"></th>
                    <th class="text-right" data-total="discount"></th><th class="text-right" data-total="total"></th>
                    <th class="text-right" data-total="refund"></th><th></th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card card-teal card-outline">
        <div class="card-header"><h3 class="card-title">Returns by product</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="return_products_table">
                <thead><tr><th>Product</th><th>SKU</th><th>Debit Notes</th><th>Qty Returned</th><th>Amount (incl. tax)</th></tr></thead>
                <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Total:</th>
                    <th class="text-right" data-total="p_qty" data-format="qty"></th><th class="text-right" data-total="p_amount"></th>
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
            var url = '{{ route('reports.purchase-returns') }}', r = 'text-right';

            RPT.server('#returns_table', url, 'list', [
                { data: 'date', name: 'date' },
                { data: 'return_no', name: 'return_no' },
                { data: 'purchase_no', name: 'purchase_no', orderable: false, searchable: false },
                { data: 'supplier_name', name: 'supplier_name', orderable: false },
                { data: 'subtotal', name: 'subtotal', render: RPT.money, className: r, searchable: false },
                { data: 'tax_amount', name: 'tax_amount', render: RPT.money, className: r, searchable: false },
                { data: 'discount_amount', name: 'discount_amount', render: RPT.money, className: r, searchable: false },
                { data: 'total', name: 'total', render: RPT.money, className: r, searchable: false },
                { data: 'refund_amount', name: 'refund_amount', render: RPT.money, className: r, searchable: false },
                { data: 'reason', name: 'reason' }
            ], { order: [[0, 'desc']] });

            RPT.client('#return_products_table', url, 'products', [
                { data: 'product' }, { data: 'sku' }, { data: 'returns', className: 'text-center' },
                { data: 'qty', render: RPT.qty, className: r }, { data: 'amount', render: RPT.money, className: r }
            ], { order: [[4, 'desc']] });
        });
    </script>
@endpush
