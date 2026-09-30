@extends('pdf.layout')

@section('doc_title', 'Local Purchase Order')
@section('doc_number', $order->lpo_no)
@section('doc_badge')
    @if ($order->status === 'cancelled')
        <div style="margin-top:6px"><span class="status status-due">Cancelled</span></div>
    @endif
@endsection

@section('content')
    <table>
        <tr>
            <td style="width:38%; padding-right:8px; vertical-align:top">
                <div class="box">
                    <div class="box-title">Supplier</div>
                    <div class="bold">{{ $order->supplier->company ?: $order->supplier->name }}</div>
                    @if ($order->supplier->company)<div>Attn: {{ $order->supplier->name }}</div>@endif
                    <div class="muted">
                        {{ $order->supplier->address }}@if ($order->supplier->city), {{ $order->supplier->city }}@endif<br>
                        {{ $order->supplier->phone }} @if ($order->supplier->email) &middot; {{ $order->supplier->email }}@endif
                        @if ($order->supplier->tax_number)<br>PIN: {{ $order->supplier->tax_number }}@endif
                    </div>
                </div>
            </td>
            <td style="width:32%; padding-right:8px; vertical-align:top">
                <div class="box">
                    <div class="box-title">Deliver to</div>
                    <div class="bold">{{ settings('business_name') }}</div>
                    <div class="muted">{{ $order->delivery_address ?: trim(settings('address').', '.settings('city'), ', ') }}</div>
                </div>
            </td>
            <td style="width:30%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">LPO No</td><td class="bold">{{ $order->lpo_no }}</td></tr>
                        <tr><td class="label">Date</td><td>{{ format_date($order->date) }}</td></tr>
                        <tr><td class="label">Expected</td><td>{{ format_date($order->expected_date) ?: '-' }}</td></tr>
                        <tr><td class="label">Supplier code</td><td>{{ $order->supplier->code }}</td></tr>
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
            <th class="text-right" style="width:10%">Qty</th>
            <th class="text-right" style="width:13%">Unit Cost</th>
            <th class="text-right" style="width:8%">Disc %</th>
            <th class="text-right" style="width:12%">Tax</th>
            <th class="text-right" style="width:14%">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($order->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product->name ?? '-' }}<div class="desc">{{ $item->product->sku ?? '' }}</div></td>
                <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                <td class="text-right">{{ num_format($item->unit_cost) }}</td>
                <td class="text-right">{{ (float) $item->discount_percent }}</td>
                <td class="text-right">{{ num_format($item->tax_amount) }}<div class="desc">{{ (float) $item->tax_rate }}%</div></td>
                <td class="text-right">{{ num_format($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top:10px">
        <tr>
            <td style="width:55%; vertical-align:top; padding-right:14px">
                @if ($order->notes)
                    <div class="notes"><div class="box-title">Notes / Instructions</div>{!! nl2br(e($order->notes)) !!}</div>
                @endif
                <div class="notes muted">
                    Please quote this LPO number on your delivery note and invoice. Goods are subject to inspection on delivery.
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="text-right">{{ money($order->subtotal) }}</td></tr>
                    @if ($order->discount_amount > 0)
                        <tr><td>Discount</td><td class="text-right">- {{ money($order->discount_amount) }}</td></tr>
                    @endif
                    <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right">{{ money($order->tax_amount) }}</td></tr>
                    <tr class="grand"><td>Total</td><td class="text-right">{{ money($order->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="signature">
        <tr>
            <td style="width:33%"><div class="line">Prepared by: {{ $order->creator->name ?? '' }}</div></td>
            <td style="width:33%"><div class="line">Authorised by</div></td>
            <td style="width:33%"><div class="line">Supplier acceptance (sign &amp; stamp)</div></td>
        </tr>
    </table>
@endsection
