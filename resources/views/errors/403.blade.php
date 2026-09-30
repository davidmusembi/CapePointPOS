@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
    <div class="card">
        <div class="card-body empty-state py-5">
            <i class="fas fa-lock d-block" style="font-size:3rem"></i>
            <h4 class="text-dark mt-3">You don't have permission to access this page</h4>
            <p>Ask an administrator to grant your role the required permission.</p>
            <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
        </div>
    </div>
@endsection
