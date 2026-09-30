{{-- Shared line-item table + totals for LPO and purchase forms. Expects $discountType, $discountValue --}}
<div class="card card-teal card-outline">
    <div class="card-header">
        <h3 class="card-title">Items <span class="badge badge-primary ml-1" id="item_count">0</span></h3>
    </div>
    <div class="card-body">
        <div class="product-search-wrap mb-3">
            <select id="product_search" class="form-control"></select>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered items-table mb-0" id="items_table">
                <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>Product</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit Cost</th>
                    <th class="text-right">Disc %</th>
                    <th>Tax</th>
                    <th class="text-right">Line Total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        @error('items')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="form-group mb-0">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" rows="4" class="form-control" placeholder="Terms, delivery instructions...">{{ old('notes', $notes ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-6">
                        <label for="discount_type">Discount type</label>
                        <select name="discount_type" id="discount_type" class="form-control custom-select">
                            <option value="fixed" @selected(old('discount_type', $discountType) === 'fixed')>Fixed amount</option>
                            <option value="percentage" @selected(old('discount_type', $discountType) === 'percentage')>Percentage (%)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="discount_value">Discount</label>
                        <input type="number" step="any" min="0" name="discount_value" id="discount_value" class="form-control text-right" value="{{ old('discount_value', (float) $discountValue) }}">
                    </div>
                </div>
                <table class="table totals-table mb-0">
                    <tr><td>Subtotal (excl. tax)</td><td class="text-right" id="subtotal_text">0.00</td></tr>
                    <tr><td>Discount</td><td class="text-right text-danger" id="discount_text">0.00</td></tr>
                    <tr><td>{{ settings('tax_label', 'Tax') }}</td><td class="text-right" id="tax_text">0.00</td></tr>
                    <tr class="grand"><td>Total</td><td class="text-right" id="total_text">0.00</td></tr>
                </table>
            </div>
        </div>
        @if (! empty($withPayment))
            @include('partials.payment-rows', ['title' => 'Payment to supplier', 'hint' => 'Leave the amount at 0 to record the purchase on credit. Split payments across methods by adding rows.'])
        @endif
    </div>
</div>
