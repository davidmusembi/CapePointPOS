@extends('pdf.layout')

@php
    $c = $return->customer;
    $taxLabel = settings('tax_label', 'Tax');
@endphp

@section('doc_title', 'Credit Note')
@section('doc_number', $return->return_no)

@section('content')
    <table>
        <tr>
            <td style="width:55%; padding-right:10px; vertical-align:top">
                <div class="box">
                    <div class="box-title">Credit to</div>
                    <div class="bold" style="font-size:11px">{{ $c->name ?? '-' }}</div>
                    @if ($c?->company)<div>{{ $c->company }}</div>@endif
                    <div class="muted">
                        @if ($c?->address || $c?->city){{ collect([$c->address, $c->city])->filter()->implode(', ') }}<br>@endif
                        @if ($c?->phone)Tel: {{ $c->phone }}@endif
                        @if ($c?->tax_number)<br>PIN: {{ $c->tax_number }}@endif
                    </div>
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">Credit note No</td><td class="bold">{{ $return->return_no }}</td></tr>
                        <tr><td class="label">Date</td><td>{{ format_date($return->date) }}</td></tr>
                        <tr><td class="label">Original invoice</td><td>{{ $return->sale->invoice_no ?? '-' }}</td></tr>
                        <tr><td class="label">Invoice date</td><td>{{ format_date($return->sale->date ?? null) }}</td></tr>
                        @if ($return->reason)<tr><td class="label">Reason</td><td>{{ $return->reason }}</td></tr>@endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th style="width:5%">#</th>
            <th>Description</th>
            <th class="text-right" style="width:12%">Qty</th>
            <th class="text-right" style="width:15%">Unit Price</th>
            <th class="text-right" style="width:13%">{{ $taxLabel }}</th>
            <th class="text-right" style="width:15%">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($return->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><span class="bold">{{ $item->product->name ?? '-' }}</span><div class="desc">{{ $item->product->sku ?? '' }}</div></td>
                <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                <td class="text-right">{{ num_format($item->unit_price) }}</td>
                <td class="text-right">{{ num_format($item->tax_amount) }}</td>
                <td class="text-right bold">{{ num_format($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top:12px">
        <tr>
            <td style="width:55%; vertical-align:top; padding-right:14px">
                @if ($return->notes)
                    <div class="box-title">Notes</div>
                    <div>{!! nl2br(e($return->notes)) !!}</div>
                @endif
            </td>
            <td style="width:45%; vertical-align:top">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="text-right">{{ money($return->subtotal) }}</td></tr>
                    <tr><td>{{ $taxLabel }}</td><td class="text-right">{{ money($return->tax_amount) }}</td></tr>
                    @if ($return->discount_amount > 0)<tr><td>Less discount share</td><td class="text-right">- {{ money($return->discount_amount) }}</td></tr>@endif
                    <tr class="grand"><td>Total credit</td><td class="text-right">{{ money($return->total) }}</td></tr>
                    <tr><td>Refunded @if ($return->refund_amount > 0)({{ payment_method_label($return->refund_method) }})@endif</td><td class="text-right">{{ money($return->refund_amount) }}</td></tr>
                    <tr><td class="bold">Credited to account</td><td class="text-right bold">{{ money($return->total - $return->refund_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="signature">
        <tr>
            <td style="width:50%"><div class="line">Authorised by: {{ $return->creator->name ?? '' }}</div></td>
            <td style="width:50%"><div class="line">Customer acknowledgement (name &amp; signature)</div></td>
        </tr>
    </table>
@endsection
