<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | {{ settings('business_name') }}</title>
    @include('layouts.partials.favicon')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo mb-4">
        @include('layouts.partials.brand', ['size' => 84])
        <div class="login-business">{{ settings('business_name') }}</div>
    </div>
    <div class="card">
        <div class="card-body login-card-body p-4" style="border-radius:16px">
            <h5 class="font-weight-bold mb-1">Welcome back</h5>
            <p class="text-muted mb-4">Sign in to the back-office</p>

            @if (session('error'))
                <div class="alert alert-danger py-2">{{ session('error') }}</div>
            @endif

            <form action="{{ route('login') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                        <input type="text" id="username" name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" placeholder="Your username" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="50" required autofocus>
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="remember" name="remember">
                        <label class="custom-control-label font-weight-normal" for="remember">Remember me</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block py-2"><i class="fas fa-sign-in-alt mr-1"></i> Sign In</button>
            </form>
        </div>
    </div>
    <p class="text-center text-white-50 small mt-3">CapePoint POS &copy; {{ date('Y') }}</p>
</div>
</body>
</html>
