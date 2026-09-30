@extends('layouts.app')

@section('title', 'Invoice '.$sale->invoice_no)

@section('header_actions')
    <a href="{{ route('sales.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route('sales.print', $sale) }}" target="_blank" class="btn btn-default btn-sm"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route('sales.print', [$sale, 'download' => 1]) }}" class="btn btn-default btn-sm"><i class="far fa-file-pdf"></i> PDF</a>
    <div class="btn-group">
        <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown">More actions</button>
        <div class="dropdown-menu dropdown-menu-right">
            @if ($sale->due_amount > 0)
                @can('payments.create')
                    <a href="#" class="dropdown-item btn-modal" data-href="{{ route('payments.create', ['type' => 'customer', 'sale_id' => $sale->id]) }}"><i class="fas fa-money-bill-wave"></i> Add Payment</a>
                @endcan
            @endif
            @can('sale_returns.create')
                <a href="{{ route('sale-returns.create', ['sale_id' => $sale->id]) }}" class="dropdown-item"><i class="fas fa-undo-alt"></i> Sales Return</a>
            @endcan
            @can('delivery_notes.create')
                <a href="{{ route('delivery-notes.create', ['sale_id' => $sale->id]) }}" class="dropdown-item"><i class="fas fa-shipping-fast"></i> Create Delivery Note</a>
            @endcan
            @if ($sale->returns->isEmpty())
                @can('sales.edit')
                    <a href="{{ route('sales.edit', $sale) }}" class="dropdown-item"><i class="fas fa-edit"></i> Edit</a>
                @endcan
            @endif
            @can('sales.delete')
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item text-danger btn-delete" data-href="{{ route('sales.destroy', $sale) }}" data-redirect="{{ route('sales.index') }}" data-message="The invoice will be deleted and its stock restored."><i class="fas fa-trash-alt text-danger"></i> Delete</a>
            @endcan
        </div>
    </div>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="doc-header mb-3">
                <div>
                    <div class="text-muted small text-uppercase font-weight-600">Bill to</div>
                    <h5 class="mb-1 font-weight-bold">
                        @can('customers.view')
                            <a href="{{ route('customers.show', $sale->customer_id) }}">{{ $sale->customer->name }}</a>
                        @else
                            {{ $sale->customer->name }}
                        @endcan
                    </h5>
                    @if ($sale->customer->company)<div>{{ $sale->customer->company }}</div>@endif
                    <div class="text-muted small">
                        {{ collect([$sale->customer->address, $sale->customer->city])->filter()->implode(', ') }}<br>
                        {{ collect([$sale->customer->phone, $sale->customer->email])->filter()->implode(' · ') }}
                        @if ($sale->customer->tax_number)<br>PIN: {{ $sale->customer->tax_number }}@endif
                    </div>
                </div>
                <dl class="doc-meta row mb-0" style="min-width:320px">
                    <div class="col-6"><dt>Invoice No</dt><dd>{{ $sale->invoice_no }}</dd></div>
                    <div class="col-6"><dt>Status</dt><dd>{!! payment_status_badge($sale->payment_status) !!} @if ($sale->is_overdue)<span class="badge badge-pill badge-danger">Overdue</span>@endif</dd></div>
                    <div class="col-6"><dt>Invoice date</dt><dd>{{ format_date($sale->date) }}</dd></div>
                    <div class="col-6"><dt>Due date</dt><dd class="{{ $sale->is_overdue ? 'text-danger' : '' }}">{{ format_date($sale->due_date) ?: '-' }}</dd></div>
                    <div class="col-6"><dt>Customer ref</dt><dd>{{ $sale->customer_reference ?: '-' }}</dd></div>
                    <div class="col-6"><dt>Added by</dt><dd>{{ $sale->creator->name ?? '-' }}</dd></div>
                </dl>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Product</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Disc</th>
                        <th class="text-right">Tax</th>
                        <th class="text-right">Line Total</th>
                        <th class="text-right">Returned</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($sale->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <div class="font-weight-600">{{ $item->product->name ?? '-' }}</div>
                                <small class="text-muted">{{ $item->product->sku ?? '' }}@if ($item->description) &middot; {{ $item->description }}@endif</small>
                            </td>
                            <td class="text-right">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                            <td class="text-right">{{ money($item->unit_price) }}</td>
                            <td class="text-right">{{ $item->discount_amount > 0 ? money($item->discount_amount).' ('.(float) $item->discount_percent.'%)' : '-' }}</td>
                            <td class="text-right">{{ money($item->tax_amount) }} <small class="text-muted">({{ (float) $item->tax_rate }}%)</small></td>
                            <td class="text-right font-weight-600">{{ money($item->line_total) }}</td>
                            <td class="text-right">{{ $item->returned_quantity > 0 ? qty_format($item->returned_quantity) : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-7">
                    @if ($sale->notes)
                        <div class="mb-3"><strong class="small text-muted text-uppercase">Notes</strong><div>{!! nl2br(e($sale->notes)) !!}</div></div>
                    @endif
                </div>
                <div class="col-md-5">
                    <table class="table totals-table">
                        <tr><td>Subtotal</td><td class="text-right">{{ money($sale->subtotal) }}</td></tr>
                        @if ($sale->discount_amount > 0)
                            <tr><td>Discount @if ($sale->discount_type === 'percentage')({{ (float) $sale->discount_value }}%)@endif</td><td class="text-right">- {{ money($sale->discount_amount) }}</td></tr>
                        @endif
                        <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right">{{ money($sale->tax_amount) }}</td></tr>
                        @if ($sale->shipping_charges > 0)<tr><td>Shipping charges</td><td class="text-right">{{ money($sale->shipping_charges) }}</td></tr>@endif
                        @foreach ($sale->additional_charges ?? [] as $charge)
                            <tr><td>{{ $charge['name'] }}</td><td class="text-right">{{ money($charge['amount']) }}</td></tr>
                        @endforeach
                        <tr class="grand"><td>Total</td><td class="text-right">{{ money($sale->total) }}</td></tr>
                        @if ($sale->returned_amount > 0)
                            <tr><td>Returned (credit notes)</td><td class="text-right">- {{ money($sale->returned_amount) }}</td></tr>
                        @endif
                        <tr><td>Paid</td><td class="text-right text-success">{{ money($sale->paid_amount) }}</td></tr>
                        <tr><td class="font-weight-bold">Balance due</td><td class="text-right font-weight-bold {{ $sale->due_amount > 0 ? 'text-danger' : '' }}">{{ money($sale->due_amount) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($sale->shipping_status || $sale->shipping_address || $sale->attachments->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shipping-fast mr-1 text-teal"></i> Shipping</h3>
                <div class="card-tools">{!! shipping_status_badge($sale->shipping_status) !!}</div>
            </div>
            <div class="card-body">
                <div class="row doc-meta">
                    <div class="col-md-4"><dl><dt>Shipping address</dt><dd>{!! nl2br(e($sale->shipping_address ?: '-')) !!}</dd></dl></div>
                    <div class="col-md-4"><dl><dt>Shipping details</dt><dd>{!! nl2br(e($sale->shipping_details ?: '-')) !!}</dd></dl></div>
                    <div class="col-md-2"><dl><dt>Delivered to</dt><dd>{{ $sale->delivered_to ?: '-' }}</dd></dl></div>
                    <div class="col-md-2"><dl><dt>Delivery person</dt><dd>{{ $sale->deliveryPerson?->name ?? '-' }}</dd></dl></div>
                    @if ($sale->attachments->isNotEmpty())
                        <div class="col-md-12">
                            <dl class="mb-0"><dt>Shipping documents</dt>
                                <dd class="mb-0">
                                    @foreach ($sale->attachments as $file)
                                        <a href="{{ route('attachments.download', $file) }}" class="btn btn-xs btn-default mr-1 mb-1"><i class="{{ $file->icon }} mr-1"></i>{{ $file->original_name }} <span class="text-muted">({{ $file->size_label }})</span></a>
                                    @endforeach
                                </dd>
                            </dl>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-teal card-outline">
                <div class="card-header">
                    <h3 class="card-title">Payments</h3>
                    <div class="card-tools">
                        @if ($sale->due_amount > 0)
                            @can('payments.create')
                                <button type="button" class="btn btn-xs btn-teal btn-modal" data-href="{{ route('payments.create', ['type' => 'customer', 'sale_id' => $sale->id]) }}"><i class="fas fa-plus"></i> Add Payment</button>
                            @endcan
                        @endif
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th class="pl-3">Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th class="text-right pr-3">Amount</th></tr></thead>
                        <tbody>
                        @forelse ($sale->allocations as $alloc)
                            <tr>
                                <td class="pl-3">
                                    @if ($alloc->payment)
                                        <a href="{{ route('payments.show', $alloc->payment) }}">{{ $alloc->payment->payment_no }}</a>
                                    @endif
                                </td>
                                <td>{{ format_date($alloc->payment->date ?? null) }}</td>
                                <td>{{ payment_method_label($alloc->payment->method ?? null) }}</td>
                                <td>{{ $alloc->payment->reference ?? '-' }}</td>
                                <td class="text-right pr-3">{{ money($alloc->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No payments recorded</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Credit notes (returns)</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th class="pl-3">Credit Note</th><th>Date</th><th class="text-right">Total</th><th class="text-right pr-3">Refunded</th></tr></thead>
                        <tbody>
                        @forelse ($sale->returns as $ret)
                            <tr>
                                <td class="pl-3"><a href="{{ route('sale-returns.show', $ret) }}">{{ $ret->return_no }}</a></td>
                                <td>{{ format_date($ret->date) }}</td>
                                <td class="text-right">{{ money($ret->total) }}</td>
                                <td class="text-right pr-3">{{ money($ret->refund_amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No returns</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Delivery notes</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th class="pl-3">DN No</th><th>Date</th><th>Driver / Vehicle</th><th class="pr-3">Status</th></tr></thead>
                        <tbody>
                        @forelse ($sale->deliveryNotes as $dn)
                            <tr>
                                <td class="pl-3"><a href="{{ route('delivery-notes.show', $dn) }}">{{ $dn->delivery_no }}</a></td>
                                <td>{{ format_date($dn->date) }}</td>
                                <td>{{ collect([$dn->deliveryPerson?->name ?? $dn->driver_name, $dn->vehicle_no])->filter()->implode(' / ') ?: '-' }}</td>
                                <td class="pr-3">{!! shipping_status_badge($dn->status) !!} @if ($dn->from_sale)<span class="badge badge-primary">linked</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No delivery notes</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        @if (request()->boolean('print'))
            window.open('{{ route('sales.print', $sale) }}', '_blank');
        @endif
        // Reload the page after a payment is saved from the modal so balances refresh.
        $(document).on('app:saved', function () { window.location.reload(); });
    });
</script>
@endpush
