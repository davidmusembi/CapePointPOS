@props(['label' => 'Date Range', 'start' => null, 'end' => null, 'col' => 'col-md-3', 'id' => 'date_range'])
{{-- Date range filter. Initialise with APP.initDateRange('#{{ $id }}', callback) --}}
<div class="{{ $col }}">
    <div class="form-group">
        <label for="{{ $id }}">{{ $label }}</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-calendar-alt"></i></span></div>
            <input type="text" id="{{ $id }}" class="form-control date-range-input" readonly>
        </div>
        <input type="hidden" name="start_date" value="{{ $start }}">
        <input type="hidden" name="end_date" value="{{ $end }}">
    </div>
</div>
