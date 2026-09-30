@extends('layouts.app')

@section('title', 'Purchase '.$purchase->purchase_no)
@section('page_subtitle', $purchase->supplier->display_name ?? '')

@section('header_actions')
    <a href="{{ route('purchases.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    @if ($purchase->due_amount > 0)
        @can('payments.create')
            <button type="button" class="btn btn-teal btn-modal" data-href="{{ route('payments.create', ['type' => 'supplier', 'purchase_id' => $purchase->id]) }}"><i class="fas fa-money-bill-wave"></i> Add Payment</button>
        @endcan
    @endif
    @can('purchase_returns.create')
        <a href="{{ route('purchase-returns.create', ['purchase_id' => $purchase->id]) }}" class="btn btn-light-primary"><i class="fas fa-reply-all"></i> Return</a>
    @endcan
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Supplier</dt>
                        <dd>
                            @can('suppliers.view')<a href="{{ route('suppliers.show', $purchase->supplier_id) }}">{{ $purchase->supplier->display_name ?? '-' }}</a>@else{{ $purchase->supplier->display_name ?? '-' }}@endcan
                            <br><small class="text-muted">{{ $purchase->supplier->phone ?? '' }}</small>
                        </dd>
                        <dt>Supplier invoice no.</dt><dd>{{ $purchase->supplier_invoice_no ?: '-' }}</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Purchase date</dt><dd>{{ format_date($purchase->date) }}</dd>
                        <dt>Due date</dt><dd>{{ format_date($purchase->due_date) ?: '-' }} @if ($purchase->is_overdue)<span class="badge badge-danger">Overdue</span>@endif</dd>
                        <dt>LPO</dt><dd>@if ($purchase->purchaseOrder)<a href="{{ route('purchase-orders.show', $purchase->purchase_order_id) }}">{{ $purchase->purchaseOrder->lpo_no }}</a>@else - @endif</dd>
                    </dl>
                </div>
                <div class="col-md-4">
                    <dl class="doc-meta">
                        <dt>Payment status</dt><dd>{!! payment_status_badge($purchase->payment_status) !!}</dd>
                        <dt>Recorded by</dt><dd>{{ $purchase->creator->name ?? '-' }} <small class="text-muted">{{ format_date($purchase->created_at, true) }}</small></dd>
                    </dl>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>#</th><th>Product</th><th class="text-right">Qty</th><th class="text-right">Returned</th><th class="text-right">Unit Cost</th>
                        <th class="text-right">Disc %</th><th class="text-right">Tax</th><th class="text-right">Line Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($purchase->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $item->product->name ?? '-' }}<br><small class="text-muted">{{ $item->product->sku ?? '' }}</small></td>
                            <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                            <td class="text-right">{{ $item->returned_quantity > 0 ? qty_format($item->returned_quantity) : '-' }}</td>
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
                    @if ($purchase->notes)
                        <strong>Notes</strong>
                        <p class="text-muted" style="white-space:pre-line">{{ $purchase->notes }}</p>
                    @endif
                </div>
                <div class="col-md-6">
                    <table class="table totals-table">
                        <tr><td>Subtotal</td><td class="text-right">{{ money($purchase->subtotal) }}</td></tr>
                        <tr><td>Discount</td><td class="text-right text-danger">- {{ money($purchase->discount_amount) }}</td></tr>
                        <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right">{{ money($purchase->tax_amount) }}</td></tr>
                        @if ($purchase->shipping_charges > 0)<tr><td>Shipping charges</td><td class="text-right">{{ money($purchase->shipping_charges) }}</td></tr>@endif
                        @foreach ($purchase->additional_charges ?? [] as $charge)
                            <tr><td>{{ $charge['name'] }}</td><td class="text-right">{{ money($charge['amount']) }}</td></tr>
                        @endforeach
                        <tr class="grand"><td>Total</td><td class="text-right">{{ money($purchase->total) }}</td></tr>
                        @if ($purchase->returned_amount > 0)
                            <tr><td>Returned (debit notes)</td><td class="text-right">- {{ money($purchase->returned_amount) }}</td></tr>
                        @endif
                        <tr><td>Paid</td><td class="text-right text-success">{{ money($purchase->paid_amount) }}</td></tr>
                        <tr><td class="font-weight-bold">Balance due</td><td class="text-right font-weight-bold {{ $purchase->due_amount > 0 ? 'text-danger' : '' }}">{{ money($purchase->due_amount) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="card-footer text-right">
            @if ($purchase->returns->isEmpty())
                @can('purchases.edit')
                    <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-default"><i class="fas fa-edit"></i> Edit</a>
                @endcan
                @can('purchases.delete')
                    <button type="button" class="btn btn-outline-danger btn-delete" data-href="{{ route('purchases.destroy', $purchase) }}" data-redirect="{{ route('purchases.index') }}" data-message="Stock received on this purchase will be reversed."><i class="fas fa-trash-alt"></i> Delete</button>
                @endcan
            @endif
        </div>
    </div>

    @include('partials.shipping-panel', ['doc' => $purchase, 'docType' => 'purchase'])

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-teal card-outline">
                <div class="card-header"><h3 class="card-title">Payments</h3></div>
                <div class="card-body p-0">
                    @if ($purchase->allocations->isEmpty())
                        <div class="empty-state"><i class="fas fa-money-bill-wave d-block"></i> No payments recorded.</div>
                    @else
                        <table class="table table-striped mb-0">
                            <thead><tr><th>Date</th><th>Payment No</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                            @foreach ($purchase->allocations as $a)
                                <tr>
                                    <td>{{ format_date($a->payment->date ?? null) }}</td>
                                    <td>@if ($a->payment)<a href="{{ route('payments.show', $a->payment) }}">{{ $a->payment->payment_no }}</a>@endif</td>
                                    <td>{{ payment_method_label($a->payment->method ?? null) }}</td>
                                    <td>{{ $a->payment->reference ?? '-' }}</td>
                                    <td class="text-right">{{ money($a->amount) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-teal card-outline">
                <div class="card-header"><h3 class="card-title">Returns (debit notes)</h3></div>
                <div class="card-body p-0">
                    @if ($purchase->returns->isEmpty())
                        <div class="empty-state"><i class="fas fa-reply-all d-block"></i> No returns.</div>
                    @else
                        <table class="table table-striped mb-0">
                            <thead><tr><th>Date</th><th>Return No</th><th class="text-right">Total</th><th class="text-right">Refund</th></tr></thead>
                            <tbody>
                            @foreach ($purchase->returns as $r)
                                <tr>
                                    <td>{{ format_date($r->date) }}</td>
                                    <td><a href="{{ route('purchase-returns.show', $r) }}">{{ $r->return_no }}</a></td>
                                    <td class="text-right">{{ money($r->total) }}</td>
                                    <td class="text-right">{{ $r->refund_amount > 0 ? money($r->refund_amount) : '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).on('app:saved', function () { window.location.reload(); });
</script>
@endpush
