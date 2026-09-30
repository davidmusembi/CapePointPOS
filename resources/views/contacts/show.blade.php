@extends('layouts.app')

@php $isCustomer = $meta['type'] === 'customer'; @endphp

@section('title', $contact->name)
@section('page_subtitle', $contact->code.($contact->company ? ' · '.$contact->company : ''))

@section('header_actions')
    <a href="{{ route($meta['route'].'.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    <a href="{{ route($meta['route'].'.statement', $contact) }}" class="btn btn-light-primary"><i class="fas fa-file-alt"></i> Statement</a>
    @can('payments.create')
        <a href="{{ route('payments.create', ['type' => $meta['type'], $meta['type'].'_id' => $contact->id]) }}" class="btn btn-teal">
            <i class="fas fa-money-bill-wave"></i> {{ $isCustomer ? 'Receive Payment' : 'Pay Supplier' }}
        </a>
    @endcan
    @if ($isCustomer)
        @can('sales.create')
            <a href="{{ route('sales.create', ['customer_id' => $contact->id]) }}" class="btn btn-primary"><i class="fas fa-file-invoice"></i> New Invoice</a>
        @endcan
    @else
        @can('purchases.create')
            <a href="{{ route('purchases.create', ['supplier_id' => $contact->id]) }}" class="btn btn-primary"><i class="fas fa-truck-loading"></i> New Purchase</a>
        @endcan
    @endif
@endsection

@section('content')
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile {{ $contact->balance > 0 ? 'tone-red' : 'tone-green' }}">
                <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
                <div>
                    <div class="stat-label">Balance ({{ $meta['balanceLabel'] }})</div>
                    <div class="stat-value">{{ money($contact->balance) }}</div>
                    @if ($contact->balance < 0)<div class="stat-sub text-success">Credit in their favour</div>@endif
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile tone-blue">
                <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <div class="stat-label">Total {{ strtolower($meta['docLabel']) }}</div>
                    <div class="stat-value">{{ money($contact->total_invoiced) }}</div>
                    <div class="stat-sub">{{ $stats['documents'] }} {{ $isCustomer ? 'invoices' : 'purchases' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile tone-teal">
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <div class="stat-label">Total paid</div>
                    <div class="stat-value">{{ money($contact->total_paid) }}</div>
                    <div class="stat-sub">Returns: {{ money($contact->total_returned) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-tile tone-amber">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value">{{ money($stats['overdue']) }}</div>
                    <div class="stat-sub">Last activity: {{ $stats['last_document'] ? format_date($stats['last_document']) : '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">{{ $meta['singular'] }} details</h3>
                    @can($meta['perm'].'.edit')
                        <div class="card-tools"><button class="btn btn-tool btn-modal" data-href="{{ route($meta['route'].'.edit', $contact) }}"><i class="fas fa-edit"></i></button></div>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm info-list mb-0">
                        <tr><th class="pl-3">Code</th><td>{{ $contact->code }}</td></tr>
                        <tr><th class="pl-3">Contact</th><td>{{ $contact->name }}</td></tr>
                        <tr><th class="pl-3">Company</th><td>{{ $contact->company ?: '-' }}</td></tr>
                        <tr><th class="pl-3">Phone</th><td>{{ $contact->phone ?: '-' }}</td></tr>
                        <tr><th class="pl-3">Email</th><td>{{ $contact->email ?: '-' }}</td></tr>
                        <tr><th class="pl-3">Address</th><td>{{ trim($contact->address.', '.$contact->city, ', ') ?: '-' }}</td></tr>
                        <tr><th class="pl-3">Tax / PIN</th><td>{{ $contact->tax_number ?: '-' }}</td></tr>
                        <tr><th class="pl-3">Pay term</th><td>{{ $contact->payment_terms !== null ? $contact->payment_terms.' days' : '-' }}</td></tr>
                        @if ($isCustomer)
                            <tr><th class="pl-3">Credit limit</th><td>{{ $contact->credit_limit ? money($contact->credit_limit) : 'No limit' }}
                                    @if ($contact->credit_limit && $contact->balance > $contact->credit_limit)<span class="badge badge-danger ml-1">Exceeded</span>@endif</td></tr>
                        @endif
                        <tr><th class="pl-3">Opening balance</th><td>{{ money($contact->opening_balance) }}</td></tr>
                        <tr><th class="pl-3">Status</th><td>{!! status_badge($contact->is_active ? 'active' : 'inactive') !!}</td></tr>
                    </table>
                    @if ($contact->notes)<div class="p-3 border-top small text-muted">{{ $contact->notes }}</div>@endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-teal card-outline">
                <div class="card-header">
                    <h3 class="card-title">Recent {{ $isCustomer ? 'invoices' : 'purchases' }}</h3>
                    <div class="card-tools">
                        <a href="{{ route($isCustomer ? 'sales.index' : 'purchases.index', [$meta['type'].'_id' => $contact->id]) }}" class="btn btn-tool">View all</a>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr><th class="pl-3">Date</th><th>{{ $isCustomer ? 'Invoice' : 'Purchase' }} No</th><th>Due date</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Due</th><th class="pr-3">Status</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($documents as $doc)
                            <tr>
                                <td class="pl-3">{{ format_date($doc->date) }}</td>
                                <td><a href="{{ route($isCustomer ? 'sales.show' : 'purchases.show', $doc) }}">{{ $isCustomer ? $doc->invoice_no : $doc->purchase_no }}</a></td>
                                <td class="{{ $doc->is_overdue ? 'text-danger font-weight-600' : '' }}">{{ format_date($doc->due_date) }}</td>
                                <td class="text-right">{{ money($doc->total) }}</td>
                                <td class="text-right">{{ money($doc->paid_amount) }}</td>
                                <td class="text-right">{{ money($doc->due_amount) }}</td>
                                <td class="pr-3">{!! payment_status_badge($doc->payment_status) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-state">No transactions yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Recent payments</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr><th class="pl-3">Date</th><th>Payment No</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th><th class="text-right pr-3">Unallocated</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($payments as $p)
                            <tr>
                                <td class="pl-3">{{ format_date($p->date) }}</td>
                                <td><a href="{{ route('payments.show', $p) }}">{{ $p->payment_no }}</a></td>
                                <td>{{ payment_method_label($p->method) }}</td>
                                <td>{{ $p->reference ?: '-' }}</td>
                                <td class="text-right">{{ money($p->amount) }}</td>
                                <td class="text-right pr-3">{{ $p->unallocated_amount > 0 ? money($p->unallocated_amount) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">No payments recorded.</td></tr>
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
    $(document).on('app:saved', function () { window.location.reload(); });
</script>
@endpush
