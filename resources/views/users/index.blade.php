@extends('layouts.app')

@section('title', 'Users')
@section('page_subtitle', 'People who can sign in to the back-office')

@section('header_actions')
    @can('users.create')
        <button class="btn btn-primary btn-modal" data-href="{{ route('users.create') }}"><i class="fas fa-user-plus"></i> Add User</button>
    @endcan
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title">All Users</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="users_table">
                <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Last login</th><th class="no-export" style="width:90px">Action</th></tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#users_table').DataTable({
            serverSide: true,
            ajax: '{{ route('users.index') }}',
            order: [[0, 'asc']],
            columns: [
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { data: 'phone', name: 'phone' },
                { data: 'role', name: 'role', orderable: false },
                { data: 'is_active', name: 'is_active', searchable: false },
                { data: 'last_login_at', name: 'last_login_at', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
