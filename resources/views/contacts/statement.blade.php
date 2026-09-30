@extends('layouts.app')

@section('title', 'Statement of Account')
@section('page_subtitle', $contact->display_name)

@section('header_actions')
    <a href="{{ route($meta['route'].'.show', $contact) }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route($meta['route'].'.statement', [$contact, 'start_date' => $from, 'end_date' => $to, 'pdf' => 1]) }}" target="_blank" class="btn btn-light-primary"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route($meta['route'].'.statement', [$contact, 'start_date' => $from, 'end_date' => $to, 'pdf' => 1, 'download' => 1]) }}" class="btn btn-primary"><i class="far fa-file-pdf"></i> Download PDF</a>
@endsection

@section('content')
    <div class="card card-filter no-print">
        <div class="card-body pb-1">
            <form method="GET" class="filters" id="statement_filter">
                <div class="row align-items-end">
                    <x-date-range :start="$from" :end="$to" col="col-md-4" />
                    <div class="col-md-2">
                        <div class="form-group"><button type="submit" class="btn btn-primary btn-block"><i class="fas fa-sync-alt"></i> Apply</button></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2"><div class="summary-box primary"><div class="label">Opening balance ({{ format_date($from) }})</div><div class="value">{{ money($statement['opening']) }}</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box warning"><div class="label">{{ $meta['type'] === 'customer' ? 'Invoiced / debits' : 'Payments & returns (debits)' }}</div><div class="value">{{ money($statement['totals']['debit']) }}</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box success"><div class="label">{{ $meta['type'] === 'customer' ? 'Paid & credited' : 'Purchases (credits)' }}</div><div class="value">{{ money($statement['totals']['credit']) }}</div></div></div>
        <div class="col-md-3 col-6 mb-2"><div class="summary-box danger"><div class="label">Closing balance ({{ format_date($to) }})</div><div class="value">{{ money($statement['closing']) }}</div></div></div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">{{ $contact->display_name }} &middot; {{ format_date($from) }} &ndash; {{ format_date($to) }}</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped w-100" id="statement_table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Balance</th>
                </tr>
                </thead>
                <tbody>
                <tr class="font-italic">
                    <td>{{ format_date($from) }}</td>
                    <td>Opening</td>
                    <td>-</td>
                    <td>Balance brought forward</td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                    <td class="text-right font-weight-600">{{ money($statement['opening']) }}</td>
                </tr>
                @foreach ($statement['entries'] as $row)
                    <tr>
                        <td data-order="{{ $row['date'] }}">{{ format_date($row['date']) }}</td>
                        <td>{{ $row['type'] }}</td>
                        <td>@if ($row['url'])<a href="{{ $row['url'] }}">{{ $row['reference'] }}</a>@else{{ $row['reference'] }}@endif</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="text-right">{{ $row['debit'] ? money($row['debit']) : '' }}</td>
                        <td class="text-right">{{ $row['credit'] ? money($row['credit']) : '' }}</td>
                        <td class="text-right font-weight-600">{{ money($row['balance']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="4" class="text-right">Totals / closing balance</th>
                    <th class="text-right">{{ money($statement['totals']['debit']) }}</th>
                    <th class="text-right">{{ money($statement['totals']['credit']) }}</th>
                    <th class="text-right">{{ money($statement['closing']) }}</th>
                </tr>
                </tfoot>
            </table>
            <p class="text-muted small mb-0 mt-2">
                @if ($meta['type'] === 'customer')
                    Positive balance = amount owed by the customer. Negative balance = customer credit.
                @else
                    Positive balance = amount owed to the supplier. Negative balance = supplier owes you (credit).
                @endif
            </p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        APP.initDateRange('#date_range');
        $('#statement_table').DataTable({ ordering: false, pageLength: -1, responsive: true });
    });
</script>
@endpush
