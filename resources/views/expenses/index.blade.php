@extends('layouts.app')

@section('title', 'Expenses')
@section('page_subtitle', 'Business running costs')

@section('header_actions')
    @can('expenses.create')
        <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Expense</a>
    @endcan
@endsection

@section('content')
    <x-filters>
        <x-date-range :start="now()->startOfMonth()->toDateString()" :end="now()->toDateString()" />
        <div class="col-md-3">
            <div class="form-group">
                <label>Category</label>
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
                    <option value="">All methods</option>
                    @foreach (payment_methods(false, false) as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">Expense list</h3>
            <div class="card-tools">
                <span class="text-muted small mr-1">Total for period:</span> <strong data-summary="amount">-</strong>
                <span class="text-muted small ml-2">(<span data-summary="count" data-format="raw">0</span> entries)</span>
                @can('reports.expenses')
                    <a href="{{ route('reports.expenses') }}" class="btn btn-tool ml-2" title="Category breakdown"><i class="fas fa-chart-pie"></i> Expense report</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="expenses_table">
                <thead>
                <tr>
                    <th>Date</th><th>Reference</th><th>Category</th><th>Payee</th><th>Method</th><th>Payment Ref</th><th>Amount</th><th>Notes</th><th>Added By</th><th class="no-export">Action</th>
                </tr>
                </thead>
                <tfoot>
                <tr><th colspan="6" class="text-right">Total</th><th class="text-right" data-total="amount"></th><th colspan="3"></th></tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var table;
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        table = $('#expenses_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('expenses.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'date', name: 'date' },
                { data: 'reference_no', name: 'reference_no' },
                { data: 'category_name', name: 'category_name', orderable: false },
                { data: 'payee', name: 'payee' },
                { data: 'payment_method', name: 'payment_method' },
                { data: 'payment_reference', name: 'payment_reference' },
                { data: 'amount', name: 'amount', className: 'text-right' },
                { data: 'notes', name: 'notes' },
                { data: 'added_by', name: 'added_by', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            drawCallback: function () { APP.renderFooterTotals('#expenses_table', this.api().ajax.json()); }
        });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
