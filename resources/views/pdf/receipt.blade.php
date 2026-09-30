@extends('pdf.layout')

@php $isCustomer = $payment->party_type === 'customer'; @endphp

@section('doc_title', $isCustomer ? 'Receipt' : 'Payment Voucher')
@section('doc_number', $payment->payment_no)

@push('styles')
    <style>
        @page { margin: 22px 26px 50px 26px; }
        .amount-box { border: 2px solid #0e9f8e; border-radius: 8px; padding: 10px; text-align: center; margin: 12px 0; }
        .amount-box .amt { font-size: 18px; font-weight: bold; color: #1b4f8a; }
    </style>
@endpush

@section('content')
    <table>
        <tr>
            <td style="width:55%; vertical-align:top; padding-right:8px">
                <div class="box">
                    <div class="box-title">{{ $isCustomer ? 'Received from' : 'Paid to' }}</div>
                    <div class="bold">{{ $party?->display_name }}</div>
                    <div class="muted">{{ $party?->phone }}</div>
                </div>
            </td>
            <td style="width:45%; vertical-align:top">
                <div class="box">
                    <table class="meta">
                        <tr><td class="label">Date</td><td class="text-right">{{ format_date($payment->date) }}</td></tr>
                        <tr><td class="label">Method</td><td class="text-right">{{ payment_method_label($payment->method) }}</td></tr>
                        @if ($payment->reference)<tr><td class="label">Reference</td><td class="text-right">{{ $payment->reference }}</td></tr>@endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="amount-box">
        <div class="muted">{{ $isCustomer ? 'Amount received' : 'Amount paid' }}</div>
        <div class="amt">{{ money($payment->amount) }}</div>
    </div>

    <table class="items">
        <thead><tr><th>{{ $isCustomer ? 'Invoice' : 'Purchase' }}</th><th>Date</th><th class="text-right">Allocated</th><th class="text-right">Balance after</th></tr></thead>
        <tbody>
        @forelse ($payment->allocations as $a)
            <tr>
                <td>{{ $a->payable->reference ?? '-' }}</td>
                <td>{{ $a->payable ? format_date($a->payable->date) : '' }}</td>
                <td class="text-right">{{ money($a->amount) }}</td>
                <td class="text-right">{{ $a->payable ? money($a->payable->due_amount) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Advance payment / on account</td></tr>
        @endforelse
        </tbody>
    </table>

    <table style="margin-top:10px">
        <tr>
            <td style="width:50%"></td>
            <td style="width:50%">
                <table class="totals">
                    @if ($payment->unallocated_amount > 0)<tr><td>Unallocated (on account)</td><td class="text-right">{{ money($payment->unallocated_amount) }}</td></tr>@endif
                    <tr class="grand"><td>Account balance</td><td class="text-right">{{ money($party?->balance ?? 0) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($payment->notes)<div class="notes"><span class="bold">Notes:</span> {{ $payment->notes }}</div>@endif

    <table class="signature">
        <tr>
            <td style="width:50%"><div class="line">{{ $isCustomer ? 'Received by' : 'Approved by' }}: {{ $payment->creator->name ?? '' }}</div></td>
            <td style="width:50%"><div class="line">{{ $isCustomer ? 'Customer signature' : 'Received by (supplier)' }}</div></td>
        </tr>
    </table>
@endsection
