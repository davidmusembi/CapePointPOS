@extends('layouts.app')

@section('title', 'Credit Note '.$return->return_no)

@section('header_actions')
    <a href="{{ route('sale-returns.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route('sale-returns.print', $return) }}" target="_blank" class="btn btn-default btn-sm"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route('sale-returns.print', [$return, 'download' => 1]) }}" class="btn btn-default btn-sm"><i class="far fa-file-pdf"></i> PDF</a>
    @can('sale_returns.delete')
        <a href="#" class="btn btn-sm btn-outline-danger btn-delete" data-href="{{ route('sale-returns.destroy', $return) }}" data-redirect="{{ route('sale-returns.index') }}"
           data-message="The credit note will be deleted and returned stock removed again."><i class="fas fa-trash-alt"></i> Delete</a>
    @endcan
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="doc-header mb-3">
                <div>
                    <div class="text-muted small text-uppercase font-weight-600">Customer</div>
                    <h5 class="mb-1 font-weight-bold">{{ $return->customer->name ?? '-' }}</h5>
                    @if ($return->customer?->company)<div>{{ $return->customer->company }}</div>@endif
                    <div class="text-muted small">{{ collect([$return->customer?->phone, $return->customer?->email])->filter()->implode(' · ') }}</div>
                </div>
                <dl class="doc-meta row mb-0" style="min-width:320px">
                    <div class="col-6"><dt>Credit note</dt><dd>{{ $return->return_no }}</dd></div>
                    <div class="col-6"><dt>Date</dt><dd>{{ format_date($return->date) }}</dd></div>
                    <div class="col-6"><dt>Invoice</dt><dd>@if ($return->sale)<a href="{{ route('sales.show', $return->sale_id) }}">{{ $return->sale->invoice_no }}</a>@else - @endif</dd></div>
                    <div class="col-6"><dt>Reason</dt><dd>{{ $return->reason ?: '-' }}</dd></div>
                    <div class="col-6"><dt>Added by</dt><dd>{{ $return->creator->name ?? '-' }}</dd></div>
                </dl>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Tax</th>
                    <th class="text-right">Amount</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($return->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product->name ?? '-' }} <small class="text-muted">{{ $item->product->sku ?? '' }}</small></td>
                        <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                        <td class="text-right">{{ money($item->unit_price) }}</td>
                        <td class="text-right">{{ money($item->tax_amount) }}</td>
                        <td class="text-right font-weight-600">{{ money($item->line_total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="row">
                <div class="col-md-7">
                    @if ($return->notes)<strong class="small text-muted text-uppercase">Notes</strong><div>{!! nl2br(e($return->notes)) !!}</div>@endif
                </div>
                <div class="col-md-5">
                    <table class="table totals-table">
                        <tr><td>Subtotal</td><td class="text-right">{{ money($return->subtotal) }}</td></tr>
                        <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right">{{ money($return->tax_amount) }}</td></tr>
                        @if ($return->discount_amount > 0)<tr><td>Less discount share</td><td class="text-right">- {{ money($return->discount_amount) }}</td></tr>@endif
                        <tr class="grand"><td>Credit total</td><td class="text-right">{{ money($return->total) }}</td></tr>
                        <tr><td>Refunded @if ($return->refund_amount > 0)({{ payment_method_label($return->refund_method) }})@endif</td><td class="text-right">{{ money($return->refund_amount) }}</td></tr>
                        <tr><td class="font-weight-bold">Credited to account</td><td class="text-right font-weight-bold">{{ money($return->total - $return->refund_amount) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
