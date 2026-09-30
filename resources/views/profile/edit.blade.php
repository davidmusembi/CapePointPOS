@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <form action="{{ route('profile.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-lg-6">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Account</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="required">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="required">Username (login)</label>
                            <input type="text" name="username" class="form-control" value="{{ old('username', $user->username) }}" required maxlength="50" autocapitalize="none" spellcheck="false">
                        </div>
                        <div class="form-group">
                            <label class="required">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <p class="text-muted small mb-0">Role: <strong>{{ $user->getRoleNames()->implode(', ') }}</strong> &middot; Last login: {{ $user->last_login_at ? format_date($user->last_login_at, true) : '-' }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-teal card-outline">
                    <div class="card-header"><h3 class="card-title">Change password</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Current password</label>
                            <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label>New password</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                        </div>
                        <div class="form-group mb-0">
                            <label>Confirm new password</label>
                            <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-right"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save changes</button></div>
    </form>
@endsection
