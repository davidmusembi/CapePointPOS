@extends('layouts.app')

@section('title', 'Delivery Note '.$note->delivery_no)

@section('header_actions')
    <a href="{{ route('delivery-notes.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route('delivery-notes.print', $note) }}" target="_blank" class="btn btn-default btn-sm"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route('delivery-notes.print', [$note, 'download' => 1]) }}" class="btn btn-default btn-sm"><i class="far fa-file-pdf"></i> PDF</a>
    @can('delivery_notes.edit')
        @if (! in_array($note->status, ['delivered', 'cancelled'], true))
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-teal dropdown-toggle" data-toggle="dropdown"><i class="fas fa-truck"></i> Update status</button>
                <div class="dropdown-menu dropdown-menu-right">
                    @foreach (\App\Models\DeliveryNote::statuses() as $key => $label)
                        @continue($key === $note->status)
                        <a href="#" class="dropdown-item btn-confirm" data-href="{{ route('delivery-notes.status', [$note, 'status' => $key]) }}" data-method="PATCH" data-reload="page" data-message="Mark {{ $note->delivery_no }} as {{ $label }}?">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        @endif
        <a href="{{ route('delivery-notes.edit', $note) }}" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
    @endcan
    @can('delivery_notes.delete')
        <a href="#" class="btn btn-sm btn-outline-danger btn-delete" data-href="{{ route('delivery-notes.destroy', $note) }}" data-redirect="{{ route('delivery-notes.index') }}"><i class="fas fa-trash-alt"></i></a>
    @endcan
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="doc-header mb-3">
                <div>
                    <div class="text-muted small text-uppercase font-weight-600">Deliver to</div>
                    <h5 class="mb-1 font-weight-bold">{{ $note->customer->display_name ?? '-' }}</h5>
                    <div>{{ $note->delivery_address ?: '-' }}</div>
                    <div class="text-muted small">Contact: {{ collect([$note->contact_person, $note->contact_phone])->filter()->implode(' · ') ?: '-' }}</div>
                </div>
                <dl class="doc-meta row mb-0" style="min-width:320px">
                    <div class="col-6"><dt>DN No</dt><dd>{{ $note->delivery_no }}</dd></div>
                    <div class="col-6"><dt>Status</dt><dd>{!! shipping_status_badge($note->status) !!}</dd></div>
                    <div class="col-6"><dt>Date</dt><dd>{{ format_date($note->date) }}</dd></div>
                    <div class="col-6"><dt>Invoice</dt><dd>@if ($note->sale)<a href="{{ route('sales.show', $note->sale_id) }}">{{ $note->sale->invoice_no }}</a>@if ($note->from_sale) <span class="badge badge-primary">linked</span>@endif @else Standalone @endif</dd></div>
                    <div class="col-6"><dt>Delivery person</dt><dd>{{ $note->deliveryPerson?->name ?? ($note->driver_name ?: '-') }}</dd></div>
                    <div class="col-6"><dt>Vehicle</dt><dd>{{ $note->vehicle_no ?: '-' }}</dd></div>
                </dl>
            </div>

            <table class="table table-bordered table-striped">
                <thead><tr><th style="width:40px">#</th><th>Product</th><th>Description</th><th class="text-right">Quantity</th></tr></thead>
                <tbody>
                @foreach ($note->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product->name ?? '-' }} <small class="text-muted">{{ $item->product->sku ?? '' }}</small></td>
                        <td>{{ $item->description ?: '-' }}</td>
                        <td class="text-right font-weight-600">{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if ($note->notes)<strong class="small text-muted text-uppercase">Notes</strong><div>{!! nl2br(e($note->notes)) !!}</div>@endif
            <div class="text-muted small mt-2">Created by {{ $note->creator->name ?? '-' }} on {{ format_date($note->created_at, true) }}</div>
        </div>
    </div>
@endsection
