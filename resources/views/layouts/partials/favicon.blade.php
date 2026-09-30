@if (settings()->logo_url)
    <link rel="icon" href="{{ settings()->logo_url }}">
    <link rel="apple-touch-icon" href="{{ settings()->logo_url }}">
@else
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
@endif
