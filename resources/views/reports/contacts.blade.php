@extends('layouts.app')

@section('title', 'Customer & Supplier Report')
@section('page_subtitle', 'Sales, purchases, returns and balances per contact')

@section('content')
    <x-filters>
        <div class="col-md-3">
            <div class="form-group">
                <label>Contact type</label>
                <select name="type" class="form-control custom-select">
                    <option value="both">Customers &amp; suppliers</option>
                    <option value="customers">Customers only</option>
                    <option value="suppliers">Suppliers only</option>
                </select>
            </div>
        </div>
        <x-date-range :start="$start" :end="$end" label="Transactions between" col="col-md-4" />
    </x-filters>

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2"><div class="summary-box accent"><div class="label">Total sales</div><div class="value" data-summary="sale">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box primary"><div class="label">Total purchases</div><div class="value" data-summary="purchase">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box danger"><div class="label">Receivable (customers)</div><div class="value" data-summary="receivable">-</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box warning"><div class="label">Payable (suppliers)</div><div class="value" data-summary="payable">-</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">Contacts</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="contacts_table">
                <thead>
                <tr><th>Contact</th><th>Code</th><th>Type</th><th>Total Purchase</th><th>Purchase Return</th><th>Total Sale</th><th>Sell Return</th><th>Opening Balance</th><th>Payments</th><th>Balance Due</th></tr>
                </thead>
                <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Total:</th>
                    <th class="text-right" data-total="purchase"></th><th class="text-right" data-total="purchase_return"></th>
                    <th class="text-right" data-total="sale"></th><th class="text-right" data-total="sale_return"></th>
                    <th class="text-right" data-total="opening"></th><th class="text-right" data-total="payments"></th><th></th>
                </tr>
                </tfoot>
            </table>
            <small class="text-muted">Totals are for the selected period; balance due is the current ledger balance (customers: owed to you, suppliers: owed by you).</small>
        </div>
    </div>
@endsection

@push('scripts')
    @include('reports.partials.js')
    <script>
        $(function () {
            RPT.init();
            var r = 'text-right';
            RPT.client('#contacts_table', '{{ route('reports.contacts') }}', null, [
                { data: 'contact' }, { data: 'code' },
                { data: 'type', render: function (d, t) { return t === 'display' ? '<span class="badge badge-' + (d === 'Customer' ? 'info' : 'primary') + '">' + d + '</span>' : d; } },
                { data: 'purchase', render: RPT.money, className: r }, { data: 'purchase_return', render: RPT.money, className: r },
                { data: 'sale', render: RPT.money, className: r }, { data: 'sale_return', render: RPT.money, className: r },
                { data: 'opening', render: RPT.money, className: r }, { data: 'payments', render: RPT.money, className: r },
                { data: 'balance', render: RPT.money, className: r + ' font-weight-bold' }
            ], { order: [[9, 'desc']] });
        });
    </script>
@endpush
