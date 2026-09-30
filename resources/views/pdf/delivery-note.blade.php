@extends('pdf.layout')

@php $c = $note->customer; @endphp

@section('doc_title', 'Delivery Note')
@section('doc_number', $note->delivery_no)

@section('content')
    <table>
        <tr>
            <td style="width:55%; padding-right:10px; vertical-align:top">
                <div class="box">
                    <div class="box-title">Deliver to</div>
                    <div class="bold" style="font-size:11px">{{ $c->name ?? '-' }}</div>
                    @if ($c?->company)<div>{{ $c->company }}</div>@endif
                    <div class="muted">
                        {{ $note->delivery_address ?: collect([$c?->address, $c?->city])->filter()->implode(', ') }}<br>
                        Contact: {{ collect([$note->contact_person, $note->contact_phone])->filter()->implode(' · ') ?: '-' }}
                    </div>
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">DN No</td><td class="bold">{{ $note->delivery_no }}</td></tr>
                        <tr><td class="label">Date</td><td>{{ format_date($note->date) }}</td></tr>
                        <tr><td class="label">Invoice No</td><td>{{ $note->sale->invoice_no ?? '-' }}</td></tr>
                        @if ($note->sale?->customer_reference)<tr><td class="label">Customer PO</td><td>{{ $note->sale->customer_reference }}</td></tr>@endif
                        <tr><td class="label">Driver</td><td>{{ ($note->deliveryPerson?->name ?? $note->driver_name) ?: '-' }}</td></tr>
                        <tr><td class="label">Vehicle</td><td>{{ $note->vehicle_no ?: '-' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th style="width:6%">#</th>
            <th style="width:22%">Item code</th>
            <th>Description</th>
            <th class="text-right" style="width:16%">Quantity</th>
            <th class="text-center" style="width:14%">Received</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($note->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product->sku ?? '-' }}</td>
                <td><span class="bold">{{ $item->product->name ?? '-' }}</span>@if ($item->description)<div class="desc">{{ $item->description }}</div>@endif</td>
                <td class="text-right bold">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                <td class="text-center">&#9744;</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:6px" class="muted">Total items: {{ $note->items->count() }} &middot; Total quantity: {{ qty_format($note->items->sum('quantity')) }}</div>

    @if ($note->notes)
        <div class="notes"><div class="box-title">Notes</div>{!! nl2br(e($note->notes)) !!}</div>
    @endif

    <div class="box" style="margin-top:20px">
        <div class="box-title">Received in good order and condition</div>
        <table class="signature">
            <tr>
                <td style="width:25%"><div class="line">Name</div></td>
                <td style="width:25%"><div class="line">Signature</div></td>
                <td style="width:25%"><div class="line">Date</div></td>
                <td style="width:25%"><div class="line">Stamp</div></td>
            </tr>
        </table>
    </div>

    <table class="signature">
        <tr>
            <td style="width:50%"><div class="line">Dispatched by: {{ $note->creator->name ?? '' }}</div></td>
            <td style="width:50%"><div class="line">Driver signature</div></td>
        </tr>
    </table>
@endsection
