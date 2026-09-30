@extends('layouts.app')

@section('title', 'LPO '.$order->lpo_no)
@section('page_subtitle', $order->supplier->display_name ?? '')

@section('header_actions')
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route('purchase-orders.print', $order) }}" target="_blank" class="btn btn-light-primary"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route('purchase-orders.print', [$order, 'download' => 1]) }}" class="btn btn-light-primary"><i class="far fa-file-pdf"></i> PDF</a>
    @if ($order->can_receive)
        @can('purchases.create')
            <a href="{{ route('purchases.create', ['purchase_order_id' => $order->id]) }}" class="btn btn-teal"><i class="fas fa-truck-loading"></i> Receive Goods</a>
        @endcan
    @endif
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Supplier</dt>
                        <dd>
                            @can('suppliers.view')<a href="{{ route('suppliers.show', $order->supplier_id) }}">{{ $order->supplier->display_name ?? '-' }}</a>@else{{ $order->supplier->display_name ?? '-' }}@endcan
                            <br><small class="text-muted">{{ $order->supplier->phone ?? '' }} {{ $order->supplier->email ?? '' }}</small>
                        </dd>
                        <dt>Deliver to</dt><dd>{{ $order->delivery_address ?: '-' }}</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>LPO date</dt><dd>{{ format_date($order->date) }}</dd>
                        <dt>Expected delivery</dt><dd>{{ format_date($order->expected_date) ?: '-' }}</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Status</dt><dd>{!! status_badge($order->status) !!}</dd>
                        <dt>Created by</dt><dd>{{ $order->creator->name ?? '-' }} <small class="text-muted">{{ format_date($order->created_at, true) }}</small></dd>
                    </dl>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>#</th><th>Product</th><th class="text-right">Ordered</th><th class="text-right">Received</th><th class="text-right">Pending</th>
                        <th class="text-right">Unit Cost</th><th class="text-right">Disc %</th><th class="text-right">Tax</th><th class="text-right">Line Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($order->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $item->product->name ?? '-' }}<br><small class="text-muted">{{ $item->product->sku ?? '' }}</small></td>
                            <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                            <td class="text-right text-success">{{ qty_format($item->received_quantity) }}</td>
                            <td class="text-right {{ $item->pending_quantity > 0 ? 'text-danger font-weight-600' : '' }}">{{ qty_format($item->pending_quantity) }}</td>
                            <td class="text-right">{{ money($item->unit_cost) }}</td>
                            <td class="text-right">{{ (float) $item->discount_percent }}</td>
                            <td class="text-right">{{ money($item->tax_amount) }} <small class="text-muted">({{ (float) $item->tax_rate }}%)</small></td>
                            <td class="text-right">{{ money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-6">
                    @if ($order->notes)
                        <strong>Notes</strong>
                        <p class="text-muted" style="white-space:pre-line">{{ $order->notes }}</p>
                    @endif
                </div>
                <div class="col-md-6">
                    <table class="table totals-table">
                        <tr><td>Subtotal</td><td class="text-right">{{ money($order->subtotal) }}</td></tr>
                        <tr><td>Discount</td><td class="text-right text-danger">- {{ money($order->discount_amount) }}</td></tr>
                        <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right">{{ money($order->tax_amount) }}</td></tr>
                        <tr class="grand"><td>Total</td><td class="text-right">{{ money($order->total) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="card-footer text-right">
            @if ($order->status === 'pending')
                @can('purchase_orders.edit')
                    <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-default"><i class="fas fa-edit"></i> Edit</a>
                @endcan
            @endif
            @if ($order->can_receive)
                @can('purchase_orders.edit')
                    <button type="button" class="btn btn-default btn-confirm" data-href="{{ route('purchase-orders.cancel', $order) }}" data-method="PATCH" data-message="Cancel LPO {{ $order->lpo_no }}?" data-reload="page"><i class="fas fa-ban"></i> Cancel LPO</button>
                @endcan
            @endif
            @if ($order->purchases->isEmpty())
                @can('purchase_orders.delete')
                    <button type="button" class="btn btn-outline-danger btn-delete" data-href="{{ route('purchase-orders.destroy', $order) }}" data-redirect="{{ route('purchase-orders.index') }}"><i class="fas fa-trash-alt"></i> Delete</button>
                @endcan
            @endif
        </div>
    </div>

    <div class="card card-teal card-outline">
        <div class="card-header"><h3 class="card-title">Goods received against this LPO</h3></div>
        <div class="card-body p-0">
            @if ($order->purchases->isEmpty())
                <div class="empty-state"><i class="fas fa-truck d-block"></i> No goods received yet.</div>
            @else
                <table class="table table-striped mb-0">
                    <thead><tr><th>Date</th><th>Purchase No</th><th>Supplier Inv.</th><th class="text-right">Total</th><th>Payment</th><th>By</th></tr></thead>
                    <tbody>
                    @foreach ($order->purchases as $p)
                        <tr>
                            <td>{{ format_date($p->date) }}</td>
                            <td><a href="{{ route('purchases.show', $p) }}">{{ $p->purchase_no }}</a></td>
                            <td>{{ $p->supplier_invoice_no ?: '-' }}</td>
                            <td class="text-right">{{ money($p->total) }}</td>
                            <td>{!! payment_status_badge($p->payment_status) !!}</td>
                            <td>{{ $p->creator->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
