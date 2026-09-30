@extends('pdf.layout')

{{--
    Delivery note - printed from the invoice (same layout idea as the reference POS):
    business header, "Delivery Note" title, invoice no & date, customer, ship-to, items with quantities only
    (no prices), then "received in good condition" / received by / date / authorised signatory.
--}}
@php
    $c = $note->customer;
    $sale = $note->sale;
@endphp

@section('doc_title', 'Delivery Note')
@section('doc_number', $sale?->invoice_no ?? $note->delivery_no)

@push('styles')
    <style>
        table.dn-items th { font-size: 10px; padding: 7px 8px; }
        table.dn-items td { font-size: 10.5px; padding: 7px 8px; }
        .ack td { padding: 12px 0 0; font-size: 10.5px; }
        .ack .fill { border-bottom: 1px dotted #9aa4b2; }
    </style>
@endpush

@section('content')
    <table>
        <tr>
            <td style="width:50%; padding-right:10px; vertical-align:top">
                <div class="box">
                    <div class="box-title">Customer</div>
                    <div class="bold" style="font-size:11px">{{ $c->name ?? '-' }}</div>
                    @if ($c?->company)<div>{{ $c->company }}</div>@endif
                    <div class="muted">
                        @if ($c?->address || $c?->city){{ collect([$c->address, $c->city])->filter()->implode(', ') }}<br>@endif
                        @if ($c?->phone)Mobile: {{ $c->phone }}@endif
                        @if ($c?->code)<br>Customer code: {{ $c->code }}@endif
                        @if ($c?->tax_number)<br>PIN: {{ $c->tax_number }}@endif
                    </div>
                </div>
            </td>
            <td style="width:50%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">Invoice No.</td><td class="bold text-right">{{ $sale?->invoice_no ?? '-' }}</td></tr>
                        <tr><td class="label">Date</td><td class="text-right">{{ format_date($sale?->date ?? $note->date) }}</td></tr>
                        <tr><td class="label">Delivery note No.</td><td class="text-right">{{ $note->delivery_no }}</td></tr>
                        @if ($sale?->customer_reference)<tr><td class="label">Customer PO / LPO</td><td class="text-right">{{ $sale->customer_reference }}</td></tr>@endif
                        @if ($sale?->creator)<tr><td class="label">Sales person</td><td class="text-right">{{ $sale->creator->name }}</td></tr>@endif
                    </table>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top:8px">
                <div class="box">
                    <div class="box-title">Ship to</div>
                    <div>{!! nl2br(e($note->delivery_address ?: collect([$c?->address, $c?->city])->filter()->implode(', ') ?: '-')) !!}</div>
                    <div class="muted">
                        Delivered to: {{ $note->contact_person ?: ($c->name ?? '-') }}@if ($note->contact_phone) &middot; {{ $note->contact_phone }}@endif
                        @if ($note->deliveryPerson?->name ?? $note->driver_name) &middot; Delivery person: {{ $note->deliveryPerson?->name ?? $note->driver_name }}@endif
                        @if ($note->vehicle_no) &middot; Vehicle: {{ $note->vehicle_no }}@endif
                    </div>
                    @if ($note->notes)<div class="muted" style="margin-top:3px">{!! nl2br(e($note->notes)) !!}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items dn-items">
        <thead>
        <tr>
            <th class="text-center" style="width:6%">#</th>
            <th style="width:64%">Product</th>
            <th class="num" style="width:30%">Quantity</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($note->items as $i => $item)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>
                    {{ $item->product->name ?? '-' }}@if ($item->product?->sku), {{ $item->product->sku }}@endif
                    @if ($item->description)<div class="desc">({{ $item->description }})</div>@endif
                </td>
                <td class="num">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
            </tr>
        @empty
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="ack" style="margin-top:14px; page-break-inside: avoid">
        <tr><td colspan="2" class="bold">Above mentioned items received in good condition</td></tr>
        <tr><td class="bold" style="width:22%">Received by:</td><td class="fill">&nbsp;</td></tr>
        <tr><td class="bold">Date:</td><td class="fill" style="width:40%">&nbsp;</td></tr>
        <tr><td class="bold">Authorized signatory:</td><td class="fill">&nbsp;</td></tr>
    </table>
@endsection
