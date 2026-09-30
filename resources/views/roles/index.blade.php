@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('page_subtitle', 'Control what each role can see and do')

@section('header_actions')
    <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add Role</a>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="roles_table">
                <thead><tr><th>Role</th><th>Users</th><th>Permissions</th><th class="no-export" style="width:90px">Action</th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    @php $super = in_array($role->name, config('pos.super_roles', ['Admin'])); @endphp
                    <tr>
                        <td class="font-weight-600">{{ $role->name }} @if ($super)<span class="badge badge-primary ml-1">Full access</span>@endif</td>
                        <td>{{ $role->users_count }}</td>
                        <td>{{ $super ? 'All' : $role->permissions_count.' / '.$totalPermissions }}</td>
                        <td>
                            @include('partials.actions', ['items' => array_filter([
                                ['label' => 'Edit', 'icon' => 'fas fa-edit', 'url' => route('roles.edit', $role)],
                                $super ? null : ['label' => 'Delete', 'delete' => route('roles.destroy', $role), 'reload' => true],
                            ])])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () { $('#roles_table').DataTable({ order: [[0, 'asc']] }); });
</script>
@endpush
