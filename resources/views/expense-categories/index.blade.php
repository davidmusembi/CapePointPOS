@extends('layouts.app')

@section('title', 'Expense Categories')

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">All Expense Categories</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-sm btn-primary btn-modal" data-href="{{ route('expense-categories.create') }}"><i class="fas fa-plus"></i> Add Category</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped table-hover w-100" id="expense_categories_table">
                <thead>
                <tr><th>Name</th><th>Code</th><th>Description</th><th>Expenses</th><th>Total Spent</th><th class="no-export" style="width:90px">Action</th></tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#expense_categories_table').DataTable({
            serverSide: true,
            ajax: '{{ route('expense-categories.index') }}',
            order: [[0, 'asc']],
            columns: [
                { data: 'name', name: 'name' },
                { data: 'code', name: 'code' },
                { data: 'description', name: 'description' },
                { data: 'expenses_count', name: 'expenses_count', searchable: false, className: 'text-center' },
                { data: 'expenses_sum_amount', name: 'expenses_sum_amount', searchable: false, className: 'text-right' },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
