{{-- Read-only shipping details on a document page. Params: $doc (Sale|Purchase), $docType ('sale'|'purchase'). --}}
@php $isSale = ($docType ?? 'sale') === 'sale'; @endphp
@if ($doc->shipping_status || $doc->shipping_address || $doc->charges_total > 0 || $doc->attachments->isNotEmpty())
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-shipping-fast mr-1 text-teal"></i> Shipping</h3>
            <div class="card-tools">{!! shipping_status_badge($doc->shipping_status) !!}</div>
        </div>
        <div class="card-body">
            <div class="row doc-meta">
                <div class="col-md-4"><dl><dt>Shipping address</dt><dd>{!! nl2br(e($doc->shipping_address ?: '-')) !!}</dd></dl></div>
                <div class="col-md-4"><dl><dt>Shipping details</dt><dd>{!! nl2br(e($doc->shipping_details ?: '-')) !!}</dd></dl></div>
                <div class="col-md-2"><dl><dt>{{ $isSale ? 'Delivered to' : 'Received by' }}</dt><dd>{{ $doc->delivered_to ?: '-' }}</dd></dl></div>
                <div class="col-md-2"><dl><dt>Delivery person</dt><dd>{{ $doc->deliveryPerson?->name ?? '-' }}</dd></dl></div>
                @if ($doc->attachments->isNotEmpty())
                    <div class="col-md-12">
                        <dl class="mb-0"><dt>Shipping documents</dt>
                            <dd class="mb-0">
                                @foreach ($doc->attachments as $file)
                                    <a href="{{ route('attachments.download', $file) }}" class="btn btn-xs btn-default mr-1 mb-1"><i class="{{ $file->icon }} mr-1"></i>{{ $file->original_name }} <span class="text-muted">({{ $file->size_label }})</span></a>
                                @endforeach
                            </dd>
                        </dl>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
