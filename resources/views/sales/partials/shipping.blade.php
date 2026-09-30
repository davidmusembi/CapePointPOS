{{-- Shipping section of the sales invoice form. A shipping status creates / updates the invoice's delivery note. --}}
@php
    $charges = old('additional_charges', $sale->additional_charges ?? []);
    $rows = max((int) config('pos.additional_charge_rows', 4), count($charges));
    $hasCharges = collect($charges)->contains(fn ($c) => (float) ($c['amount'] ?? 0) > 0);
    $attachCfg = config('pos.attachments');
    $shippingNote = $sale->exists ? $sale->shippingNote()->first() : null;
@endphp
<div class="card card-teal card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-shipping-fast mr-1 text-teal"></i> Shipping &amp; additional expenses</h3>
        <div class="card-tools small text-muted">
            @if ($shippingNote)
                Delivery note: <a href="{{ route('delivery-notes.show', $shippingNote) }}" target="_blank">{{ $shippingNote->delivery_no }}</a>
            @else
                Choosing a shipping status creates the delivery note automatically.
            @endif
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="shipping_details">Shipping details</label>
                    <textarea name="shipping_details" id="shipping_details" rows="3" class="form-control" maxlength="2000" placeholder="Shipping details">{{ old('shipping_details', $sale->shipping_details) }}</textarea>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="shipping_address">Shipping address</label>
                    <textarea name="shipping_address" id="shipping_address" rows="3" class="form-control" maxlength="1000" placeholder="Shipping address">{{ old('shipping_address', $sale->shipping_address) }}</textarea>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="shipping_charges">Shipping charges</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text" data-toggle="tooltip" title="Added to the invoice total after tax"><i class="fas fa-info"></i></span></div>
                        <input type="number" step="any" min="0" name="shipping_charges" id="shipping_charges" class="form-control text-right" value="{{ old('shipping_charges', (float) $sale->shipping_charges) }}">
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group">
                    <label for="shipping_status">Shipping status</label>
                    <select name="shipping_status" id="shipping_status" class="form-control custom-select">
                        <option value="">Please select</option>
                        @foreach (shipping_statuses() as $key => $label)
                            <option value="{{ $key }}" @selected(old('shipping_status', $sale->shipping_status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="delivered_to">Delivered to</label>
                    <input type="text" name="delivered_to" id="delivered_to" class="form-control" maxlength="190" placeholder="Delivered to" value="{{ old('delivered_to', $sale->delivered_to) }}">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="delivery_person_id">Delivery person</label>
                    <select name="delivery_person_id" id="delivery_person_id" class="form-control select2" data-allow-clear="true" data-placeholder="Please select">
                        <option value=""></option>
                        @foreach ($deliveryPeople as $id => $name)
                            <option value="{{ $id }}" @selected(old('delivery_person_id', $sale->delivery_person_id) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-group">
                    <label for="shipping_documents">Shipping documents</label>
                    <div class="custom-file">
                        <input type="file" name="shipping_documents[]" id="shipping_documents" class="custom-file-input" multiple
                               accept="{{ collect($attachCfg['mimes'])->map(fn ($m) => '.'.$m)->implode(',') }}">
                        <label class="custom-file-label" for="shipping_documents">Choose files...</label>
                    </div>
                    <small class="text-muted d-block mt-1">
                        Max file size: {{ round($attachCfg['max_kb'] / 1024) }}MB &middot; Allowed: {{ collect($attachCfg['mimes'])->map(fn ($m) => '.'.$m)->implode(', ') }}
                    </small>
                    <div class="mt-2" id="attachment_list">
                        @forelse ($sale->exists ? $sale->attachments : [] as $file)
                            <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1 small attachment-row">
                                <a href="{{ route('attachments.download', $file) }}"><i class="{{ $file->icon }} mr-1"></i>{{ $file->original_name }}</a>
                                <span class="text-muted ml-2 nowrap">{{ $file->size_label }}
                                    @can('sales.edit')
                                        <a href="#" class="text-danger ml-2 btn-remove-attachment" data-href="{{ route('attachments.destroy', $file) }}" title="Remove"><i class="fas fa-times"></i></a>
                                    @endcan
                                </span>
                            </div>
                        @empty
                            <div class="text-muted small text-center border-top pt-2">No attachment found</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="text-center mb-2">
                    <button type="button" class="btn btn-sm btn-light-primary" data-toggle="collapse" data-target="#additional_charges_box" aria-expanded="{{ $hasCharges ? 'true' : 'false' }}">
                        <i class="fas fa-plus"></i> Add additional expenses <i class="fas fa-chevron-down ml-1"></i>
                    </button>
                </div>
                <div class="collapse {{ $hasCharges ? 'show' : '' }}" id="additional_charges_box">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Additional expense name</th><th style="width:35%">Amount</th></tr></thead>
                        <tbody>
                        @for ($i = 0; $i < $rows; $i++)
                            <tr>
                                <td><input type="text" name="additional_charges[{{ $i }}][name]" class="form-control" maxlength="120" value="{{ $charges[$i]['name'] ?? '' }}"></td>
                                <td><input type="number" step="any" min="0" name="additional_charges[{{ $i }}][amount]" class="form-control text-right additional-charge-amount" value="{{ (float) ($charges[$i]['amount'] ?? 0) }}"></td>
                            </tr>
                        @endfor
                        </tbody>
                    </table>
                    <small class="text-muted">Added to the invoice total after tax (e.g. packaging, handling, insurance).</small>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(function () {
        // file picker label
        $('#shipping_documents').on('change', function () {
            var n = this.files.length;
            $(this).next('.custom-file-label').text(n ? (n === 1 ? this.files[0].name : n + ' files selected') : 'Choose files...');
        });

        // remove an existing attachment
        $(document).on('click', '.btn-remove-attachment', function (e) {
            e.preventDefault();
            var $btn = $(this);
            APP.confirm({ text: 'Remove this document?', confirmButtonText: 'Yes, remove' }).then(function (r) {
                if (!r.isConfirmed) { return; }
                $.ajax({ url: $btn.data('href'), method: 'POST', data: { _method: 'DELETE' } })
                    .done(function (res) { toastr.success(res.message); $btn.closest('.attachment-row').remove(); })
                    .fail(function (xhr) { APP.handleError(xhr); });
            });
        });

        // default the shipping address / recipient from the chosen customer
        $('#customer_id').on('select2:select', function (e) {
            var c = e.params.data;
            if (!$.trim($('#shipping_address').val()) && c.address) { $('#shipping_address').val(c.address); }
            if (!$.trim($('#delivered_to').val()) && c.text) { $('#delivered_to').val(c.text); }
        });
    });
</script>
@endpush
