@extends('layouts.app')

@section('title', 'Stock Adjustment '.$adjustment->reference_no)

@section('header_actions')
    <a href="{{ route('stock-adjustments.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
    <button onclick="window.print()" class="btn btn-light-primary"><i class="fas fa-print"></i> Print</button>
    @can('stock_adjustments.delete')
        <button class="btn btn-danger btn-delete" data-href="{{ route('stock-adjustments.destroy', $adjustment) }}" data-redirect="{{ route('stock-adjustments.index') }}" data-message="The stock movement will be reversed."><i class="fas fa-trash-alt"></i> Delete</button>
    @endcan
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="row doc-meta mb-3">
                <div class="col-md-3"><dl><dt>Reference</dt><dd>{{ $adjustment->reference_no }}</dd></dl></div>
                <div class="col-md-3"><dl><dt>Date</dt><dd>{{ format_date($adjustment->date) }}</dd></dl></div>
                <div class="col-md-3"><dl><dt>Type</dt><dd>{!! $adjustment->type === 'increase' ? '<span class="badge badge-success">Increase</span>' : '<span class="badge badge-danger">Decrease</span>' !!}</dd></dl></div>
                <div class="col-md-3"><dl><dt>Reason</dt><dd>{{ $adjustment->reason }}</dd></dl></div>
                <div class="col-md-3"><dl><dt>Added by</dt><dd>{{ $adjustment->creator->name ?? '-' }}</dd></dl></div>
                @if ($adjustment->notes)<div class="col-md-9"><dl><dt>Notes</dt><dd>{{ $adjustment->notes }}</dd></dl></div>@endif
            </div>
            <table class="table table-bordered table-striped">
                <thead><tr><th>#</th><th>Product</th><th>SKU</th><th class="text-right">Quantity</th><th class="text-right">Unit cost</th><th class="text-right">Value</th></tr></thead>
                <tbody>
                @foreach ($adjustment->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><a href="{{ route('products.show', $item->product_id) }}">{{ $item->product->name }}</a></td>
                        <td>{{ $item->product->sku }}</td>
                        <td class="text-right">{{ $adjustment->type === 'increase' ? '+' : '-' }}{{ qty_format($item->quantity) }} {{ $item->product->unit->short_name ?? '' }}</td>
                        <td class="text-right">{{ money($item->unit_cost) }}</td>
                        <td class="text-right">{{ money($item->subtotal) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><th colspan="5" class="text-right">Total value</th><th class="text-right">{{ money($adjustment->total_amount) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
@endsection
