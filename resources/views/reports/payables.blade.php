@extends('layouts.app')

@section('title', 'Supplier Payables')
@section('page_subtitle', 'Outstanding amounts owed to suppliers')

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" label="Purchase date" />
        @include('reports.partials.contact-filter', ['contacts' => $suppliers, 'name' => 'supplier_id', 'label' => 'Supplier'])
    </x-filters>

    <div class="row mb-3">
        <div class="col-md col-6 mb-2"><div class="summary-box primary"><div class="label">Suppliers owed</div><div class="value" data-summary="s_suppliers" data-format="raw">0</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box danger"><div class="label">Due on purchases</div><div class="value" data-summary="s_due">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box danger"><div class="label">Overdue</div><div class="value" data-summary="s_overdue">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box accent"><div class="label">{{ $labels['b30'] }}</div><div class="value" data-summary="s_b30">-</div></div></div>
        <div class="col-md col-6 mb-2"><div class="summary-box warning"><div class="label">Over {{ config('pos.aging_boundaries')[0] ?? 30 }} days</div><div class="value" id="over30">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab_suppliers" role="tab"><i class="fas fa-industry mr-1"></i> By supplier</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab_invoices" role="tab"><i class="fas fa-file-invoice mr-1"></i> By purchase</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_suppliers" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="suppliers_table">
                        <thead>
                        <tr><th>Supplier</th><th>Code</th><th>Phone</th><th>Purchases Due</th><th>{{ $labels['b30'] }}</th><th>{{ $labels['b60'] }}</th><th>{{ $labels['b90'] }}</th><th>{{ $labels['b90p'] }}</th><th>Due on Purchases</th><th>Overdue</th><th>Ledger Balance</th><th class="no-export">Action</th></tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">Total:</th>
                            <th class="text-center" data-total="s_count" data-format="raw"></th>
                            <th class="text-right" data-total="s_b30"></th><th class="text-right" data-total="s_b60"></th>
                            <th class="text-right" data-total="s_b90"></th><th class="text-right" data-total="s_b90p"></th>
                            <th class="text-right" data-total="s_due"></th><th class="text-right" data-total="s_overdue"></th>
                            <th class="text-right" data-total="s_balance"></th><th></th>
                        </tr>
                        </tfoot>
                    </table>
                    <small class="text-muted">Aging buckets are based on purchase date as of today. Ledger balance includes opening balances and unallocated payments.</small>
                </div>
                <div class="tab-pane fade" id="tab_invoices" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="invoices_table">
                        <thead>
                        <tr><th>Purchase No.</th><th>Supplier Inv.</th><th>Date</th><th>Due Date</th><th>Overdue</th><th>Supplier</th><th>Total</th><th>Paid</th><th>Returned</th><th>Due</th></tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="6" class="text-right">Total:</th>
                            <th class="text-right" data-total="total"></th><th class="text-right" data-total="paid"></th>
                            <th class="text-right" data-total="returned"></th><th class="text-right" data-total="due"></th>
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
            var url = '{{ route('reports.payables') }}', r = 'text-right';

            RPT.client('#suppliers_table', url, 'suppliers', [
                { data: 'supplier' }, { data: 'code' }, { data: 'phone' },
                { data: 'count', className: 'text-center' },
                { data: 'b30', render: RPT.money, className: r }, { data: 'b60', render: RPT.money, className: r },
                { data: 'b90', render: RPT.money, className: r }, { data: 'b90p', render: RPT.money, className: r },
                { data: 'due', render: RPT.money, className: r + ' font-weight-bold' },
                { data: 'overdue', render: RPT.money, className: r },
                { data: 'balance', render: RPT.money, className: r },
                { data: 'action', orderable: false, searchable: false }
            ], {
                order: [[8, 'desc']],
                onData: function (json) {
                    var t = json.totals || {};
                    $('#over30').text(APP.formatMoney((t.s_b60 || 0) + (t.s_b90 || 0) + (t.s_b90p || 0)));
                }
            });

            RPT.server('#invoices_table', url, 'invoices', [
                { data: 'purchase_no', name: 'purchase_no' },
                { data: 'supplier_invoice_no', name: 'supplier_invoice_no' },
                { data: 'date', name: 'date' },
                { data: 'due_date', name: 'due_date' },
                { data: 'days_overdue', name: 'days_overdue', orderable: false, searchable: false },
                { data: 'supplier_name', name: 'supplier_name', orderable: false },
                { data: 'total', name: 'total', render: RPT.money, className: r, searchable: false },
                { data: 'paid_amount', name: 'paid_amount', render: RPT.money, className: r, searchable: false },
                { data: 'returned_amount', name: 'returned_amount', render: RPT.money, className: r, searchable: false },
                { data: 'due_amount', name: 'due_amount', render: RPT.money, className: r, searchable: false }
            ], { order: [[3, 'asc']] });
        });
    </script>
@endpush
