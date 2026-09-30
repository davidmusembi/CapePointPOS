@extends('pdf.layout')

@section('doc_title', 'Debit Note')
@section('doc_number', $return->return_no)

@section('content')
    <table>
        <tr>
            <td style="width:55%; padding-right:8px; vertical-align:top">
                <div class="box">
                    <div class="box-title">Supplier</div>
                    <div class="bold">{{ $return->supplier->company ?: $return->supplier->name }}</div>
                    <div class="muted">
                        {{ $return->supplier->address }}@if ($return->supplier->city), {{ $return->supplier->city }}@endif<br>
                        {{ $return->supplier->phone }} @if ($return->supplier->email) &middot; {{ $return->supplier->email }}@endif
                        @if ($return->supplier->tax_number)<br>PIN: {{ $return->supplier->tax_number }}@endif
                    </div>
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">Debit note no</td><td class="bold">{{ $return->return_no }}</td></tr>
                        <tr><td class="label">Date</td><td>{{ format_date($return->date) }}</td></tr>
                        <tr><td class="label">Original purchase</td><td>{{ $return->purchase->purchase_no ?? '-' }}</td></tr>
                        <tr><td class="label">Supplier invoice</td><td>{{ $return->purchase->supplier_invoice_no ?? '-' }}</td></tr>
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
            <th class="text-right" style="width:15%">Unit Cost</th>
            <th class="text-right" style="width:13%">Tax</th>
            <th class="text-right" style="width:15%">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($return->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product->name ?? '-' }}<div class="desc">{{ $item->product->sku ?? '' }}</div></td>
                <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                <td class="text-right">{{ num_format($item->unit_cost) }}</td>
                <td class="text-right">{{ num_format($item->tax_amount) }}</td>
                <td class="text-right">{{ num_format($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table style="margin-top:10px">
        <tr>
            <td style="width:55%; vertical-align:top; padding-right:14px">
                <div class="notes">
                    @if ($return->reason)<div class="box-title">Reason for return</div>{{ $return->reason }}<br><br>@endif
                    @if ($return->notes){!! nl2br(e($return->notes)) !!}<br><br>@endif
                    @if ($return->refund_amount > 0)
                        Refund received: <strong>{{ money($return->refund_amount) }}</strong> ({{ payment_method_label($return->refund_method) }})
                    @else
                        <span class="muted">Please credit our account with the amount above.</span>
                    @endif
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="text-right">{{ money($return->subtotal) }}</td></tr>
                    <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right">{{ money($return->tax_amount) }}</td></tr>
                    @if ($return->discount_amount > 0)
                        <tr><td>Discount</td><td class="text-right">- {{ money($return->discount_amount) }}</td></tr>
                    @endif
                    <tr class="grand"><td>Total</td><td class="text-right">{{ money($return->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="signature">
        <tr>
            <td style="width:50%"><div class="line">Issued by: {{ $return->creator->name ?? '' }}</div></td>
            <td style="width:50%"><div class="line">Received by supplier (sign &amp; stamp)</div></td>
        </tr>
    </table>
@endsection
