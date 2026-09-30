{{-- Brand mark: business logo if uploaded, otherwise initials. $size in px. --}}
@php
    $size = $size ?? 40;
    $logo = settings()->logo_url;
    $initials = collect(preg_split('/\s+/', trim((string) settings('business_name'))))
        ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'CP';
@endphp
@if ($logo)
    <span class="brand-logo" style="width:{{ $size }}px;height:{{ $size }}px"><img src="{{ $logo }}" alt="{{ settings('business_name') }}"></span>
@else
    <span class="brand-mark" style="width:{{ $size }}px;height:{{ $size }}px">{{ $initials }}</span>
@endif
