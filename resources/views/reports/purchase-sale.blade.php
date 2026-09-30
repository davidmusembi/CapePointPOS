@extends('layouts.app')

@section('title', 'Purchase & Sale Report')
@section('page_subtitle', 'Purchases vs sales for the selected period')

@section('header_actions')
    <button type="button" class="btn btn-default no-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
@endsection

@section('content')
    <x-filters>
        <x-date-range :start="$start" :end="$end" col="col-md-4" />
    </x-filters>

    <div class="row">
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-truck-loading mr-1 text-primary"></i> Purchases</h3></div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tr><th class="pl-3">Number of purchases</th><td class="text-right pr-3" data-summary="purchase_count" data-format="raw">-</td></tr>
                        <tr><th class="pl-3">Total purchase (excl. tax, after discount)</th><td class="text-right pr-3" data-summary="purchase_excl">-</td></tr>
                        <tr><th class="pl-3">Input tax</th><td class="text-right pr-3" data-summary="purchase_tax">-</td></tr>
                        <tr><th class="pl-3">Shipping &amp; additional charges</th><td class="text-right pr-3" data-summary="purchase_charges">-</td></tr>
                        <tr><th class="pl-3">Purchase total (incl. tax &amp; charges)</th><td class="text-right pr-3 font-weight-bold" data-summary="purchase_incl">-</td></tr>
                        <tr><th class="pl-3">Total purchase return</th><td class="text-right pr-3" data-summary="purchase_return">-</td></tr>
                        <tr><th class="pl-3">Purchase due</th><td class="text-right pr-3 text-danger font-weight-bold" data-summary="purchase_due">-</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-teal card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1 text-teal"></i> Sales</h3></div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <tr><th class="pl-3">Number of invoices</th><td class="text-right pr-3" data-summary="sale_count" data-format="raw">-</td></tr>
                        <tr><th class="pl-3">Total sale (excl. tax, after discount)</th><td class="text-right pr-3" data-summary="sale_excl">-</td></tr>
                        <tr><th class="pl-3">Output tax</th><td class="text-right pr-3" data-summary="sale_tax">-</td></tr>
                        <tr><th class="pl-3">Shipping &amp; additional charges</th><td class="text-right pr-3" data-summary="sale_charges">-</td></tr>
                        <tr><th class="pl-3">Sale total (incl. tax &amp; charges)</th><td class="text-right pr-3 font-weight-bold" data-summary="sale_incl">-</td></tr>
                        <tr><th class="pl-3">Total sell return</th><td class="text-right pr-3" data-summary="sale_return">-</td></tr>
                        <tr><th class="pl-3">Sale due</th><td class="text-right pr-3 text-danger font-weight-bold" data-summary="sale_due">-</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Overall <small class="text-muted">((Sale − Sell Return) − (Purchase − Purchase Return))</small></h3></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <div class="summary-box accent">
                        <div class="label">Sale − Purchase</div>
                        <div class="value" data-summary="overall">-</div>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="summary-box danger">
                        <div class="label">Due amount (Sale due − Purchase due)</div>
                        <div class="value" data-summary="overall_due">-</div>
                    </div>
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
            RPT.fetch('{{ route('reports.purchase-sale') }}');
        });
    </script>
@endpush
