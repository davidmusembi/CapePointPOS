@extends('pdf.layout')

@php
    $c = $sale->customer;
    $taxLabel = settings('tax_label', 'Tax');
    $terms = $sale->payment_terms ?? $c->payment_terms;
    $showDiscount = $sale->items->sum('discount_amount') > 0;
@endphp

@section('doc_title', 'Invoice')
@section('doc_number', $sale->invoice_no)

@section('content')
    <table>
        <tr>
            <td style="width:55%; padding-right:10px; vertical-align:top">
                <div class="box">
                    <div class="box-title">Bill to</div>
                    <div class="bold" style="font-size:11px">{{ $c->name }}</div>
                    @if ($c->company)<div>{{ $c->company }}</div>@endif
                    <div class="muted">
                        @if ($c->address || $c->city){{ collect([$c->address, $c->city])->filter()->implode(', ') }}<br>@endif
                        @if ($c->phone)Tel: {{ $c->phone }}@endif @if ($c->email) &middot; {{ $c->email }}@endif
                        @if ($c->tax_number)<br>PIN: {{ $c->tax_number }}@endif
                    </div>
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">Invoice No</td><td class="bold">{{ $sale->invoice_no }}</td></tr>
                        <tr><td class="label">Invoice date</td><td>{{ format_date($sale->date) }}</td></tr>
                        <tr><td class="label">Due date</td><td>{{ format_date($sale->due_date) ?: '-' }}</td></tr>
                        @if ($terms !== null)<tr><td class="label">Pay term</td><td>{{ $terms }} days</td></tr>@endif
                        @if ($sale->customer_reference)<tr><td class="label">Your ref (PO/LPO)</td><td>{{ $sale->customer_reference }}</td></tr>@endif
                        <tr><td class="label">Customer code</td><td>{{ $c->code }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th style="width:4%">#</th>
            <th>Description</th>
            <th class="num" style="width:10%">Qty</th>
            <th class="num" style="width:13%">Unit Price</th>
            @if ($showDiscount)<th class="num" style="width:12%">Discount</th>@endif
            <th class="num" style="width:13%">{{ $taxLabel }}</th>
            <th class="num" style="width:15%">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($sale->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <span class="bold">{{ $item->product->name ?? '-' }}</span>
                    <div class="desc">{{ $item->product->sku ?? '' }}@if ($item->description) &middot; {{ $item->description }}@endif</div>
                </td>
                <td class="num">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                <td class="num">{{ num_format($item->unit_price) }}</td>
                @if ($showDiscount)
                    <td class="num">
                        @if ($item->discount_amount > 0)
                            {{ num_format($item->discount_amount) }}
                            @if ($item->discount_percent > 0)<div class="desc">{{ (float) $item->discount_percent }}%</div>@endif
                        @else - @endif
                    </td>
                @endif
                <td class="num">{{ num_format($item->tax_amount) }}<div class="desc">{{ (float) $item->tax_rate }}%</div></td>
                <td class="num bold">{{ num_format($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top:12px">
        <tr>
            <td style="width:55%; vertical-align:top; padding-right:14px">
                @if ($sale->allocations->isNotEmpty())
                    <div class="box" style="margin-bottom:8px">
                        <div class="box-title">Payments received</div>
                        <table class="meta">
                            @foreach ($sale->allocations as $a)
                                <tr>
                                    <td>{{ format_date($a->payment->date ?? null) }} &middot; {{ payment_method_label($a->payment->method ?? null) }}@if ($a->payment?->reference) ({{ $a->payment->reference }})@endif</td>
                                    <td class="text-right">{{ money($a->amount) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif
                @if ($sale->shipping_address || $sale->shipping_status)
                    <div class="box" style="margin-bottom:8px">
                        <div class="box-title">Ship to</div>
                        <div>{!! nl2br(e($sale->shipping_address ?: collect([$c->address, $c->city])->filter()->implode(', '))) !!}</div>
                        @if ($sale->delivered_to)<div class="muted">Attn: {{ $sale->delivered_to }}</div>@endif
                        @if ($sale->shipping_details)<div class="muted">{{ $sale->shipping_details }}</div>@endif
                    </div>
                @endif
                @if ($sale->notes)
                    <div class="box-title">Notes</div>
                    <div style="margin-bottom:8px">{!! nl2br(e($sale->notes)) !!}</div>
                @endif
            </td>
            <td style="width:45%; vertical-align:top">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="text-right">{{ money($sale->subtotal) }}</td></tr>
                    @if ($sale->discount_amount > 0)
                        <tr><td>Invoice discount @if ($sale->discount_type === 'percentage')({{ (float) $sale->discount_value }}%)@endif</td><td class="text-right">- {{ money($sale->discount_amount) }}</td></tr>
                    @endif
                    <tr><td>{{ $taxLabel }}</td><td class="text-right">{{ money($sale->tax_amount) }}</td></tr>
                    @if ($sale->shipping_charges > 0)<tr><td>Shipping charges</td><td class="text-right">{{ money($sale->shipping_charges) }}</td></tr>@endif
                    @foreach ($sale->additional_charges ?? [] as $charge)
                        <tr><td>{{ $charge['name'] }}</td><td class="text-right">{{ money($charge['amount']) }}</td></tr>
                    @endforeach
                    <tr class="grand"><td>Total</td><td class="text-right">{{ money($sale->total) }}</td></tr>
                    @if ($sale->returned_amount > 0)
                        <tr><td>Less: credit notes</td><td class="text-right">- {{ money($sale->returned_amount) }}</td></tr>
                    @endif
                    <tr><td>Amount paid</td><td class="text-right">{{ money($sale->paid_amount) }}</td></tr>
                    <tr class="due"><td>Balance due</td><td class="text-right">{{ money($sale->due_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if (settings('invoice_terms'))
        <div class="notes">
            <div class="box-title">Terms &amp; conditions</div>
            <div class="muted">{!! nl2br(e(settings('invoice_terms'))) !!}</div>
        </div>
    @endif
@endsection
