@extends('pdf.layout')

@section('doc_title', 'Statement')
@section('doc_number', format_date($from).' - '.format_date($to))

@section('content')
    <table>
        <tr>
            <td style="width:55%; padding-right:10px; vertical-align:top">
                <div class="box">
                    <div class="box-title">{{ $meta['singular'] }}</div>
                    <div class="bold">{{ $contact->company ?: $contact->name }}</div>
                    @if ($contact->company)<div>Attn: {{ $contact->name }}</div>@endif
                    <div class="muted">
                        {{ trim($contact->address.', '.$contact->city, ', ') }}<br>
                        @if ($contact->phone)Tel: {{ $contact->phone }}@endif @if ($contact->email) &middot; {{ $contact->email }}@endif
                        @if ($contact->tax_number)<br>PIN: {{ $contact->tax_number }}@endif
                    </div>
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">Account code</td><td class="text-right bold">{{ $contact->code }}</td></tr>
                        <tr><td class="label">Statement date</td><td class="text-right">{{ format_date(now()) }}</td></tr>
                        <tr><td class="label">Opening balance</td><td class="text-right">{{ money($statement['opening']) }}</td></tr>
                        <tr><td class="label bold">Closing balance</td><td class="text-right bold primary">{{ money($statement['closing']) }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th style="width:12%">Date</th>
            <th style="width:12%">Type</th>
            <th style="width:16%">Reference</th>
            <th>Description</th>
            <th class="text-right" style="width:13%">Debit</th>
            <th class="text-right" style="width:13%">Credit</th>
            <th class="text-right" style="width:14%">Balance</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ format_date($from) }}</td><td>Opening</td><td>-</td><td>Balance brought forward</td><td></td><td></td>
            <td class="text-right bold">{{ money($statement['opening']) }}</td>
        </tr>
        @foreach ($statement['entries'] as $row)
            <tr>
                <td>{{ format_date($row['date']) }}</td>
                <td>{{ $row['type'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="text-right">{{ $row['debit'] ? money($row['debit'], false) : '' }}</td>
                <td class="text-right">{{ $row['credit'] ? money($row['credit'], false) : '' }}</td>
                <td class="text-right bold">{{ money($row['balance'], false) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top:10px">
        <tr>
            <td style="width:55%"></td>
            <td style="width:45%">
                <table class="totals">
                    <tr><td>Total debits</td><td class="text-right">{{ money($statement['totals']['debit']) }}</td></tr>
                    <tr><td>Total credits</td><td class="text-right">{{ money($statement['totals']['credit']) }}</td></tr>
                    <tr class="grand"><td>Balance {{ $meta['type'] === 'customer' ? 'due' : 'payable' }}</td><td class="text-right">{{ money($statement['closing']) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="notes muted">
        @if ($meta['type'] === 'customer')
            Please review this statement and notify us of any discrepancies within 7 days. A positive balance is the amount due to {{ settings('business_name') }}.
        @else
            A positive balance is the amount payable by {{ settings('business_name') }} to the supplier.
        @endif
    </div>
@endsection
