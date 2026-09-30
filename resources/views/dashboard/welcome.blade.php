@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body empty-state py-5">
            <i class="fas fa-hand-sparkles d-block" style="font-size:3rem;color:#0e9f8e"></i>
            <h4 class="text-dark mt-3">Welcome, {{ auth()->user()->name }}</h4>
            <p>Use the menu on the left to get started.</p>
        </div>
    </div>
@endsection
