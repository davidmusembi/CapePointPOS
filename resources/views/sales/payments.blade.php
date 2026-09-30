<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Payments &mdash; {{ $sale->invoice_no }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="row mb-3">
                <div class="col-sm-4"><div class="summary-box primary"><div class="label">Invoice total</div><div class="value">{{ money($sale->total) }}</div></div></div>
                <div class="col-sm-4"><div class="summary-box success"><div class="label">Paid</div><div class="value">{{ money($sale->paid_amount) }}</div></div></div>
                <div class="col-sm-4"><div class="summary-box danger"><div class="label">Balance due</div><div class="value">{{ money($sale->due_amount) }}</div></div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="text-right">Allocated</th>
                        <th>By</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($sale->allocations as $alloc)
                        @php $payment = $alloc->payment; @endphp
                        <tr>
                            <td>@if ($payment)<a href="{{ route('payments.show', $payment) }}">{{ $payment->payment_no }}</a>@endif</td>
                            <td>{{ format_date($payment->date ?? null) }}</td>
                            <td>{{ payment_method_label($payment->method ?? null) }}</td>
                            <td>{{ $payment->reference ?? '-' }}</td>
                            <td class="text-right">{{ money($alloc->amount) }}</td>
                            <td>{{ $payment->creator->name ?? '-' }}</td>
                            <td class="text-center nowrap">
                                @if ($payment)
                                    <a href="{{ route('payments.print', $payment) }}" target="_blank" class="btn btn-xs btn-default" title="Print receipt"><i class="fas fa-print"></i></a>
                                    @can('payments.delete')
                                        <a href="#" class="btn btn-xs btn-default text-danger btn-delete" data-href="{{ route('payments.destroy', $payment) }}" data-reload="page"
                                           data-message="Receipt {{ $payment->payment_no }} ({{ money($payment->amount) }}) will be deleted and removed from all invoices it was allocated to." title="Delete"><i class="fas fa-trash-alt"></i></a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No payments recorded for this invoice</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            @if ($sale->due_amount > 0)
                @can('payments.create')
                    <button type="button" class="btn btn-teal btn-modal" data-href="{{ route('payments.create', ['type' => 'customer', 'sale_id' => $sale->id]) }}"><i class="fas fa-plus"></i> Add Payment</button>
                @endcan
            @endif
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
    </div>
</div>
