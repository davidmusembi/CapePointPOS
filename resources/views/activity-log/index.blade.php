@extends('layouts.app')

@section('title', 'Activity Log')
@section('page_subtitle', 'Who changed what, and when')

@section('content')
    <x-filters>
        <x-date-range :start="now()->startOfMonth()->toDateString()" :end="now()->toDateString()" />
        <div class="col-md-3">
            <div class="form-group">
                <label>Module</label>
                <select name="log_name" class="form-control custom-select">
                    <option value="">All</option>
                    @foreach ($logNames as $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>User</label>
                <select name="causer_id" class="form-control custom-select">
                    <option value="">All users</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-filters>

    <div class="card card-primary card-outline">
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="activity_table">
                <thead><tr><th>Date</th><th>User</th><th>Module</th><th>Action</th><th>Changes</th></tr></thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        var table;
        APP.initDateRange('#date_range', function () { table.ajax.reload(); });
        table = $('#activity_table').DataTable({
            serverSide: true,
            ajax: { url: '{{ route('activity-log.index') }}', data: function (d) { $.extend(d, APP.filters('#filters_form')); } },
            order: [[0, 'desc']],
            columns: [
                { data: 'created_at', name: 'created_at' },
                { data: 'user', name: 'user', orderable: false, searchable: false },
                { data: 'log_name', name: 'log_name' },
                { data: 'description', name: 'description' },
                { data: 'changes', name: 'changes', orderable: false, searchable: false }
            ]
        });
        $('#filters_form').on('change', 'select', function () { table.ajax.reload(); });
    });
</script>
@endpush
