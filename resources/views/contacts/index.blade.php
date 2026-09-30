@extends('layouts.app')

@section('title', $meta['plural'])
@section('page_subtitle', 'Manage your '.strtolower($meta['plural']))

@section('header_actions')
    @can($meta['perm'].'.create')
        <button type="button" class="btn btn-primary btn-modal" data-href="{{ route($meta['route'].'.create') }}"><i class="fas fa-plus"></i> Add {{ $meta['singular'] }}</button>
    @endcan
@endsection

@section('content')
    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2"><div class="summary-box primary"><div class="label">Total {{ strtolower($meta['plural']) }}</div><div class="value">{{ number_format($summary['count']) }}</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box danger"><div class="label">Total {{ strtolower($meta['balanceLabel']) }}</div><div class="value">{{ money($summary['outstanding']) }}</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box warning"><div class="label">With outstanding balance</div><div class="value">{{ number_format($summary['with_balance']) }}</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box success"><div class="label">Advance / credit balances</div><div class="value">{{ money($summary['credit']) }}</div></div></div>
    </div>

    <x-filters>
        <div class="col-md-3">
            <div class="form-group">
                <label>Balance</label>
                <select name="balance" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="due">With {{ strtolower($meta['balanceLabel']) }} balance</option>
                    <option value="credit">With credit balance</option>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control custom-select">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All {{ $meta['plural'] }}</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="contacts_table">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>City</th>
                    <th>Terms (days)</th>
                    <th>Total {{ $meta['docLabel'] }}</th>
                    <th>Total Paid</th>
                    <th>Balance ({{ $meta['balanceLabel'] }})</th>
                    <th class="no-export" style="width:90px">Action</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var table = $('#contacts_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route($meta['route'].'.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[1, 'asc']],
            columns: [
                { data: 'code', name: 'code' },
                { data: 'name', name: 'name' },
                { data: 'phone', name: 'phone' },
                { data: 'email', name: 'email' },
                { data: 'city', name: 'city' },
                { data: 'payment_terms', name: 'payment_terms', className: 'text-center' },
                { data: 'total_invoiced', name: 'total_invoiced', className: 'text-right', searchable: false },
                { data: 'total_paid', name: 'total_paid', className: 'text-right', searchable: false },
                { data: 'balance', name: 'balance', className: 'text-right', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
