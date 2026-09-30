@extends('layouts.app')

@section('title', $role->exists ? 'Edit Role: '.$role->name : 'Add Role')

@php $super = $role->exists && in_array($role->name, config('pos.super_roles', ['Admin'])); @endphp

@section('header_actions')
    <a href="{{ route('roles.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    <form action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}" method="POST">
        @csrf
        @if ($role->exists) @method('PUT') @endif
        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required">Role name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $role->name) }}" required @readonly($super)>
                        </div>
                    </div>
                    <div class="col-md-8 text-md-right">
                        <div class="form-group">
                            <button type="button" class="btn btn-sm btn-light-primary" id="check_all"><i class="fas fa-check-double"></i> Select all</button>
                            <button type="button" class="btn btn-sm btn-default" id="uncheck_all">Clear all</button>
                        </div>
                    </div>
                </div>
                @if ($super)
                    <div class="alert alert-info py-2"><i class="fas fa-info-circle mr-1"></i> The {{ $role->name }} role always has full access, regardless of the boxes below.</div>
                @endif

                <div class="row">
                    @foreach (config('pos.permissions') as $group => $permissions)
                        <div class="col-lg-4 col-md-6">
                            <div class="permission-group">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">{{ $group }}</h6>
                                    <a href="#" class="small toggle-group">Toggle</a>
                                </div>
                                @foreach ($permissions as $name => $label)
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="perm_{{ str_replace('.', '_', $name) }}" name="permissions[]" value="{{ $name }}" @checked(in_array($name, old('permissions', $granted)))>
                                        <label class="custom-control-label font-weight-normal" for="perm_{{ str_replace('.', '_', $name) }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save role</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    $('#check_all').on('click', function () { $('input[name="permissions[]"]').prop('checked', true); });
    $('#uncheck_all').on('click', function () { $('input[name="permissions[]"]').prop('checked', false); });
    $('.toggle-group').on('click', function (e) {
        e.preventDefault();
        var $boxes = $(this).closest('.permission-group').find('input[type=checkbox]');
        $boxes.prop('checked', $boxes.filter(':checked').length !== $boxes.length);
    });
</script>
@endpush
