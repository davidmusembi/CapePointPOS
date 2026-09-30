<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Payments &mdash; {{ $purchase->purchase_no }} <small class="text-muted">{{ $purchase->supplier->display_name ?? '' }}</small></h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="row mb-3">
                <div class="col-4"><div class="summary-box primary"><div class="label">Total</div><div class="value">{{ money($purchase->net_total) }}</div></div></div>
                <div class="col-4"><div class="summary-box success"><div class="label">Paid</div><div class="value">{{ money($purchase->paid_amount) }}</div></div></div>
                <div class="col-4"><div class="summary-box danger"><div class="label">Due</div><div class="value">{{ money($purchase->due_amount) }}</div></div></div>
            </div>
            @if ($purchase->allocations->isEmpty())
                <div class="empty-state"><i class="fas fa-money-bill-wave d-block"></i> No payments recorded for this purchase.</div>
            @else
                <table class="table table-bordered table-striped mb-0">
                    <thead>
                    <tr><th>Date</th><th>Payment No</th><th>Method</th><th>Reference</th><th class="text-right">Allocated</th><th>By</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($purchase->allocations as $a)
                        @php $pay = $a->payment; @endphp
                        <tr>
                            <td>{{ format_date($pay->date ?? null) }}</td>
                            <td>@if ($pay)<a href="{{ route('payments.show', $pay) }}">{{ $pay->payment_no }}</a>@endif</td>
                            <td>{{ payment_method_label($pay->method ?? null) }}</td>
                            <td>{{ $pay->reference ?? '-' }}</td>
                            <td class="text-right">{{ money($a->amount) }}</td>
                            <td>{{ $pay->creator->name ?? '-' }}</td>
                            <td class="text-nowrap">
                                @if ($pay)
                                    <a href="{{ route('payments.print', $pay) }}" target="_blank" class="btn btn-xs btn-default" title="Print voucher"><i class="fas fa-print"></i></a>
                                    @can('payments.delete')
                                        <button type="button" class="btn btn-xs btn-outline-danger btn-delete" data-href="{{ route('payments.destroy', $pay) }}" data-reload="page"
                                                data-message="Payment {{ $pay->payment_no }} ({{ money($pay->amount) }}) will be deleted and all its allocations reversed."><i class="fas fa-trash-alt"></i></button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        <div class="modal-footer">
            @if ($purchase->due_amount > 0)
                @can('payments.create')
                    <button type="button" class="btn btn-teal btn-modal" data-href="{{ route('payments.create', ['type' => 'supplier', 'purchase_id' => $purchase->id]) }}"><i class="fas fa-plus"></i> Add Payment</button>
                @endcan
            @endif
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
    </div>
</div>
