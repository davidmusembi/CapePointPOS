@extends('layouts.app')

@section('title', $order->exists ? 'Edit LPO '.$order->lpo_no : 'New Purchase Order (LPO)')

@section('header_actions')
    <a href="{{ route('purchase-orders.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back to LPOs</a>
@endsection

@section('content')
    <form id="doc-form" action="{{ $order->exists ? route('purchase-orders.update', $order) : route('purchase-orders.store') }}" method="POST">
        @csrf
        @if ($order->exists) @method('PUT') @endif

        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required" for="supplier_id">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="form-control" required data-placeholder="Search supplier">
                                @if ($supplier)
                                    <option value="{{ $supplier->id }}" selected>{{ $supplier->display_name }}</option>
                                @endif
                            </select>
                            @error('supplier_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="required" for="date">LPO date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', optional($order->date)->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="expected_date">Expected delivery</label>
                            <input type="date" name="expected_date" id="expected_date" class="form-control @error('expected_date') is-invalid @enderror" value="{{ old('expected_date', optional($order->expected_date)->toDateString()) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="delivery_address">Deliver to</label>
                            <input type="text" name="delivery_address" id="delivery_address" class="form-control" value="{{ old('delivery_address', $order->delivery_address ?? trim(settings('address').', '.settings('city'), ', ')) }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('purchases._items', ['discountType' => $order->discount_type ?? 'fixed', 'discountValue' => $order->discount_value ?? 0, 'notes' => $order->notes, 'withPayment' => false])

        <div class="text-right mb-4">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> {{ $order->exists ? 'Update LPO' : 'Save LPO' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script src="{{ asset('js/doc-form.js') }}?v={{ filemtime(public_path('js/doc-form.js')) }}"></script>
<script>
    $(function () {
        APP.contactSelect('#supplier_id', '{{ route('suppliers.search') }}');
        DocForm.init({
            priceName: 'unit_cost',
            priceField: 'cost_price',
            checkStock: false,
            searchUrl: '{{ route('products.search') }}',
            taxRates: @json($taxRates),
            items: @json($items)
        });
    });
</script>
@endpush
