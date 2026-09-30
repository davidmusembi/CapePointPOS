<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ settings('business_name') }}</title>
    @include('layouts.partials.favicon')
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
{{-- Standalone error layout: must render for guests and without any session-dependent partials. --}}
<body class="hold-transition login-page">
<div class="login-box" style="width:460px;max-width:92vw">
    <div class="card">
        <div class="card-body text-center p-5">
            <i class="@yield('icon', 'fas fa-exclamation-circle') d-block mb-3" style="font-size:3rem;color:#0e9f8e"></i>
            <h4 class="font-weight-bold">@yield('heading')</h4>
            <p class="text-muted">@yield('message')</p>
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> {{ auth()->check() ? 'Back to dashboard' : 'Go to sign in' }}
            </a>
        </div>
    </div>
</div>
</body>
</html>
