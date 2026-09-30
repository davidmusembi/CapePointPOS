@extends('layouts.app')

@section('title', 'Debit Note '.$return->return_no)
@section('page_subtitle', $return->supplier->display_name ?? '')

@section('header_actions')
    <a href="{{ route('purchase-returns.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route('purchase-returns.print', $return) }}" target="_blank" class="btn btn-light-primary"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route('purchase-returns.print', [$return, 'download' => 1]) }}" class="btn btn-light-primary"><i class="far fa-file-pdf"></i> PDF</a>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Supplier</dt><dd>{{ $return->supplier->display_name ?? '-' }}</dd>
                        <dt>Purchase</dt><dd>@if ($return->purchase)<a href="{{ route('purchases.show', $return->purchase_id) }}">{{ $return->purchase->purchase_no }}</a>@else - @endif</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Return date</dt><dd>{{ format_date($return->date) }}</dd>
                        <dt>Reason</dt><dd>{{ $return->reason ?: '-' }}</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Refund received</dt><dd>{{ $return->refund_amount > 0 ? money($return->refund_amount).' ('.payment_method_label($return->refund_method).')' : 'None - credited to account' }}</dd>
                        <dt>Recorded by</dt><dd>{{ $return->creator->name ?? '-' }} <small class="text-muted">{{ format_date($return->created_at, true) }}</small></dd>
                    </dl>
                </div>
            </div>

            <table class="table table-bordered table-striped">
                <thead><tr><th>#</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Unit Cost</th><th class="text-right">Tax</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                @foreach ($return->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product->name ?? '-' }}<br><small class="text-muted">{{ $item->product->sku ?? '' }}</small></td>
                        <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                        <td class="text-right">{{ money($item->unit_cost) }}</td>
                        <td class="text-right">{{ money($item->tax_amount) }}</td>
                        <td class="text-right">{{ money($item->line_total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="row">
                <div class="col-md-6">
                    @if ($return->notes)<strong>Notes</strong><p class="text-muted" style="white-space:pre-line">{{ $return->notes }}</p>@endif
                </div>
                <div class="col-md-6">
                    <table class="table totals-table">
                        <tr><td>Subtotal</td><td class="text-right">{{ money($return->subtotal) }}</td></tr>
                        <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right">{{ money($return->tax_amount) }}</td></tr>
                        @if ($return->discount_amount > 0)<tr><td>Discount share</td><td class="text-right text-danger">- {{ money($return->discount_amount) }}</td></tr>@endif
                        <tr class="grand"><td>Total</td><td class="text-right">{{ money($return->total) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
        @can('purchase_returns.delete')
            <div class="card-footer text-right">
                <button type="button" class="btn btn-outline-danger btn-delete" data-href="{{ route('purchase-returns.destroy', $return) }}" data-redirect="{{ route('purchase-returns.index') }}" data-message="Returned stock will be added back to inventory."><i class="fas fa-trash-alt"></i> Delete</button>
            </div>
        @endcan
    </div>
@endsection
