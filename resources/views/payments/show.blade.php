@extends('layouts.app')

@php
    $isCustomer = $payment->party_type === 'customer';
    $party = $payment->party;
@endphp

@section('title', ($isCustomer ? 'Receipt ' : 'Payment ').$payment->payment_no)

@section('header_actions')
    <a href="{{ route('payments.index', ['type' => $payment->party_type]) }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route('payments.print', $payment) }}" target="_blank" class="btn btn-light-primary"><i class="fas fa-print"></i> Print</a>
    <a href="{{ route('payments.print', [$payment, 'download' => 1]) }}" class="btn btn-light-primary"><i class="far fa-file-pdf"></i> PDF</a>
    @can('payments.delete')
        <button class="btn btn-danger btn-delete" data-href="{{ route('payments.destroy', [$payment, 'redirect' => 1]) }}" data-message="The payment and its invoice allocations will be removed."><i class="fas fa-trash-alt"></i> Delete</button>
    @endcan
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="text-muted small text-uppercase font-weight-600">{{ $isCustomer ? 'Amount received' : 'Amount paid' }}</div>
                        <div class="display-5 font-weight-bold text-primary" style="font-size:2rem">{{ money($payment->amount) }}</div>
                        <span class="badge badge-primary">{{ payment_method_label($payment->method) }}</span>
                    </div>
                    <table class="table table-sm info-list mb-0">
                        <tr><th>Payment no</th><td>{{ $payment->payment_no }}</td></tr>
                        <tr><th>Date</th><td>{{ format_date($payment->date) }}</td></tr>
                        <tr><th>{{ $isCustomer ? 'Customer' : 'Supplier' }}</th><td>
                                @if ($party)<a href="{{ route($isCustomer ? 'customers.show' : 'suppliers.show', $party) }}">{{ $party->display_name }}</a>@endif
                            </td></tr>
                        <tr><th>Reference</th><td>{{ $payment->reference ?: '-' }}</td></tr>
                        <tr><th>Recorded</th><td>{{ $payment->source === 'invoice' ? 'On invoice' : 'Payment entry' }}</td></tr>
                        <tr><th>Added by</th><td>{{ $payment->creator->name ?? '-' }}<br><small class="text-muted">{{ format_date($payment->created_at, true) }}</small></td></tr>
                        @if ($payment->notes)<tr><th>Notes</th><td>{{ $payment->notes }}</td></tr>@endif
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card card-teal card-outline">
                <div class="card-header"><h3 class="card-title">Allocation</h3></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr><th class="pl-3">{{ $isCustomer ? 'Invoice' : 'Purchase' }}</th><th>Date</th><th class="text-right">Document total</th><th class="text-right">Current due</th><th class="text-right pr-3">Allocated</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($payment->allocations as $a)
                            @php $doc = $a->payable; @endphp
                            <tr>
                                <td class="pl-3">
                                    @if ($doc)
                                        <a href="{{ route($isCustomer ? 'sales.show' : 'purchases.show', $doc) }}">{{ $doc->reference }}</a>
                                        @if ($doc->trashed())<span class="badge badge-dark">Deleted</span>@endif
                                    @else - @endif
                                </td>
                                <td>{{ $doc ? format_date($doc->date) : '' }}</td>
                                <td class="text-right">{{ $doc ? money($doc->total) : '' }}</td>
                                <td class="text-right">{{ $doc ? money($doc->due_amount) : '' }}</td>
                                <td class="text-right pr-3 font-weight-600">{{ money($a->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Not allocated to any document.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr><th colspan="4" class="text-right">Allocated</th><th class="text-right pr-3">{{ money($payment->allocated_amount) }}</th></tr>
                        <tr><th colspan="4" class="text-right">Unallocated (advance / credit)</th><th class="text-right pr-3">{{ money($payment->unallocated_amount) }}</th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
