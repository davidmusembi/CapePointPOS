@props([
    'prefix' => null,          // e.g. "payments[0]" -> payments[0][amount]; null -> plain field names
    'values' => [],
    'dateField' => 'paid_on',
    'noteField' => 'note',
    'amountId' => null,
    'amountMax' => null,
    'required' => false,
])
{{-- Global payment fields layout (invoice / purchase payment rows, Receive / Pay page, Add Payment modal). --}}
@php
    $name = fn ($f) => $prefix ? "{$prefix}[{$f}]" : $f;
    $val = fn ($f, $default = null) => old($prefix ? str_replace(['[', ']'], ['.', ''], $prefix).'.'.$f : $f, $values[$f] ?? $default);
    $methods = payment_methods();
    $currentMethod = $val('method', array_key_first($methods));
    if ($currentMethod && ! isset($methods[$currentMethod])) {
        $methods[$currentMethod] = payment_method_label($currentMethod); // keep a now-disabled method on existing payments
    }
@endphp
<div class="row payment-fields-grid">
    <div class="col-md-6">
        <div class="form-group">
            <label class="required">Amount</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-money-bill-alt"></i></span></div>
                <input type="number" step="any" min="0" @if ($amountMax !== null) max="{{ $amountMax }}" @endif
                       name="{{ $name('amount') }}" @if ($amountId) id="{{ $amountId }}" @endif
                       class="form-control text-right pay-amount" value="{{ $val('amount') }}" placeholder="0.00" @required($required)>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="required">Paid on</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-calendar-alt"></i></span></div>
                <input type="date" name="{{ $name($dateField) }}" class="form-control pay-date"
                       value="{{ $val($dateField, now()->toDateString()) }}" max="{{ now()->addDay()->toDateString() }}" required>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="required">Payment method</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-credit-card"></i></span></div>
                <select name="{{ $name('method') }}" class="form-control custom-select pay-method" required>
                    @foreach ($methods as $key => $label)
                        <option value="{{ $key }}" @selected($currentMethod === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label>Transaction reference</label>
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-hashtag"></i></span></div>
                <input type="text" name="{{ $name('reference') }}" class="form-control" maxlength="190" value="{{ $val('reference') }}" placeholder="Bank ref / mobile money code / cheque no.">
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            <label>Payment note</label>
            <textarea name="{{ $name($noteField) }}" rows="2" class="form-control" maxlength="1000">{{ $val($noteField) }}</textarea>
        </div>
    </div>
</div>
