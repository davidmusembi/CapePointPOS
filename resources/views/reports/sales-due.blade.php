@extends('layouts.app')

@section('title', 'Sales Due Report')
@section('page_subtitle', 'Outstanding receivables by customer and invoice')

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" label="Invoice date" />
        @include('reports.partials.contact-filter', ['contacts' => $customers, 'name' => 'customer_id', 'label' => 'Customer'])
    </x-filters>

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2"><div class="summary-box primary"><div class="label">Customers owing</div><div class="value" data-summary="c_customers" data-format="raw">0</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box warning"><div class="label">Unpaid invoices</div><div class="value" data-summary="c_count" data-format="raw">0</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box danger"><div class="label">Total due on invoices</div><div class="value" data-summary="c_due">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box danger"><div class="label">Overdue</div><div class="value" data-summary="c_overdue">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab_customers" role="tab"><i class="fas fa-user-friends mr-1"></i> By customer</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab_invoices" role="tab"><i class="fas fa-file-invoice mr-1"></i> By invoice</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab_customers" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="customers_table">
                        <thead>
                        <tr><th>Customer</th><th>Code</th><th>Phone</th><th>Invoices Due</th><th>Due on Invoices</th><th>Oldest Due Date</th><th>Overdue</th><th>Ledger Balance</th><th class="no-export">Action</th></tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">Total:</th>
                            <th class="text-center" data-total="c_count" data-format="raw"></th><th class="text-right" data-total="c_due"></th><th></th>
                            <th class="text-right" data-total="c_overdue"></th><th class="text-right" data-total="c_balance"></th><th></th>
                        </tr>
                        </tfoot>
                    </table>
                    <small class="text-muted">Ledger balance includes opening balances, unallocated receipts and credit notes on paid invoices.</small>
                </div>
                <div class="tab-pane fade" id="tab_invoices" role="tabpanel">
                    <table class="table table-bordered table-striped table-hover w-100" id="invoices_table">
                        <thead>
                        <tr><th>Invoice No.</th><th>Date</th><th>Due Date</th><th>Overdue</th><th>Customer</th><th>Total</th><th>Paid</th><th>Returned</th><th>Due</th><th>Status</th></tr>
                        </thead>
                        <tfoot>
                        <tr>
                            <th colspan="5" class="text-right">Total:</th>
                            <th class="text-right" data-total="total"></th><th class="text-right" data-total="paid"></th>
                            <th class="text-right" data-total="returned"></th><th class="text-right" data-total="due"></th><th></th>
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
            var url = '{{ route('reports.sales-due') }}', r = 'text-right';

            RPT.client('#customers_table', url, 'customers', [
                { data: 'customer' }, { data: 'code' }, { data: 'phone' },
                { data: 'count', className: 'text-center' },
                { data: 'due', render: RPT.money, className: r },
                { data: 'oldest_due', render: RPT.sortable },
                { data: 'overdue', render: RPT.money, className: r },
                { data: 'balance', render: RPT.money, className: r },
                { data: 'action', orderable: false, searchable: false }
            ], { order: [[4, 'desc']] });

            RPT.server('#invoices_table', url, 'invoices', [
                { data: 'invoice_no', name: 'invoice_no' },
                { data: 'date', name: 'date' },
                { data: 'due_date', name: 'due_date' },
                { data: 'days_overdue', name: 'days_overdue', orderable: false, searchable: false },
                { data: 'customer_name', name: 'customer_name', orderable: false },
                { data: 'total', name: 'total', render: RPT.money, className: r, searchable: false },
                { data: 'paid_amount', name: 'paid_amount', render: RPT.money, className: r, searchable: false },
                { data: 'returned_amount', name: 'returned_amount', render: RPT.money, className: r, searchable: false },
                { data: 'due_amount', name: 'due_amount', render: RPT.money, className: r, searchable: false },
                { data: 'payment_status', name: 'payment_status', searchable: false }
            ], { order: [[2, 'asc']] });
        });
    </script>
@endpush
