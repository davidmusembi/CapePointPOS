@extends('layouts.app')

@section('title', $product->exists ? 'Edit Product' : 'Add Product')

@section('header_actions')
    <a href="{{ route('products.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back to products</a>
@endsection

@section('content')
    <form action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" method="POST">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="form-section-title">Product details</div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="required" for="name">Product name</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name) }}" required maxlength="190">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="sku">SKU</label>
                            <input type="text" id="sku" name="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku', $product->sku) }}" placeholder="Auto-generate if blank">
                            @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="barcode">Barcode</label>
                            <input type="text" id="barcode" name="barcode" class="form-control" value="{{ old('barcode', $product->barcode) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="form-control select2" data-allow-clear="true" data-placeholder="Select category">
                                <option value=""></option>
                                @foreach ($categories as $id => $name)
                                    <option value="{{ $id }}" @selected(old('category_id', $product->category_id) == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required" for="unit_id">Unit</label>
                            <select id="unit_id" name="unit_id" class="form-control select2 @error('unit_id') is-invalid @enderror" required data-placeholder="Select unit">
                                <option value=""></option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected(old('unit_id', $product->unit_id) == $unit->id)>{{ $unit->name }} ({{ $unit->short_name }})</option>
                                @endforeach
                            </select>
                            @error('unit_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="tax_rate_id">Applicable tax</label>
                            <select id="tax_rate_id" name="tax_rate_id" class="form-control custom-select">
                                <option value="">None (tax exempt)</option>
                                @foreach ($taxRates as $tax)
                                    <option value="{{ $tax->id }}" @selected(old('tax_rate_id', $product->tax_rate_id) == $tax->id)>{{ $tax->label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $product->description) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section-title">Pricing &amp; stock</div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="required" for="cost_price">Cost price (excl. tax)</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">{{ settings('currency_symbol') }}</span></div>
                                <input type="number" step="any" min="0" id="cost_price" name="cost_price" class="form-control @error('cost_price') is-invalid @enderror" value="{{ old('cost_price', $product->exists ? round($product->cost_price, 2) : '') }}" required>
                            </div>
                            @if ($product->exists)<small class="text-muted">Weighted average, updated automatically on purchases.</small>@endif
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="required" for="selling_price">Selling price (excl. tax)</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">{{ settings('currency_symbol') }}</span></div>
                                <input type="number" step="any" min="0" id="selling_price" name="selling_price" class="form-control @error('selling_price') is-invalid @enderror" value="{{ old('selling_price', $product->selling_price) }}" required>
                            </div>
                            <small class="text-muted">Margin: <span id="margin_text">-</span></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="alert_quantity">Alert quantity</label>
                            <input type="number" step="any" min="0" id="alert_quantity" name="alert_quantity" class="form-control" value="{{ old('alert_quantity', (float) $product->alert_quantity) }}">
                            <small class="text-muted">Low-stock alert when stock falls to this level.</small>
                        </div>
                    </div>
                    @unless ($product->exists)
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="opening_stock">Opening stock</label>
                                <input type="number" step="any" min="0" id="opening_stock" name="opening_stock" class="form-control" value="{{ old('opening_stock', 0) }}">
                            </div>
                        </div>
                    @else
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Current stock</label>
                                <input type="text" class="form-control" value="{{ qty_format($product->stock_quantity) }}" readonly>
                                <small class="text-muted">Change via purchases, sales or stock adjustments.</small>
                            </div>
                        </div>
                    @endunless
                    <div class="col-md-3">
                        <div class="custom-control custom-switch mt-2">
                            <input type="hidden" name="track_stock" value="0">
                            <input type="checkbox" class="custom-control-input" id="track_stock" name="track_stock" value="1" @checked(old('track_stock', $product->track_stock))>
                            <label class="custom-control-label" for="track_stock">Manage stock</label>
                        </div>
                        <small class="text-muted">Turn off for services / non-stock items.</small>
                    </div>
                    <div class="col-md-3">
                        <div class="custom-control custom-switch mt-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                @unless ($product->exists)
                    <button type="submit" name="submit_action" value="add_another" class="btn btn-default"><i class="fas fa-plus"></i> Save &amp; add another</button>
                @endunless
                <button type="submit" name="submit_action" value="save" class="btn btn-primary"><i class="fas fa-save"></i> Save product</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $(function () {
        function margin() {
            var c = parseFloat($('#cost_price').val()) || 0, s = parseFloat($('#selling_price').val()) || 0;
            $('#margin_text').text(s > 0 ? APP.formatMoney(s - c) + ' (' + APP.round((s - c) / s * 100, 1) + '%)' : '-');
        }
        $('#cost_price, #selling_price').on('input', margin);
        margin();
    });
</script>
@endpush
