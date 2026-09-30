@extends('layouts.app')

@section('title', $expense->exists ? 'Edit Expense' : 'Add Expense')
@section('page_subtitle', $expense->reference_no)

@section('header_actions')
    <a href="{{ route('expenses.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back</a>
@endsection

@section('content')
    <form action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" method="POST">
        @csrf
        @if ($expense->exists) @method('PUT') @endif
        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required">Expense category</label>
                            <div class="input-group">
                                <select name="expense_category_id" id="expense_category_id" class="form-control select2" required data-placeholder="Select category">
                                    <option value=""></option>
                                    @foreach ($categories as $id => $name)
                                        <option value="{{ $id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @can('expense_categories.manage')
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-default btn-modal" data-href="{{ route('expense-categories.create') }}" title="Add category"><i class="fas fa-plus text-primary"></i></button>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required">Date</label>
                            <input type="date" name="date" class="form-control" value="{{ old('date', optional($expense->date)->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required">Amount</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">{{ settings('currency_symbol') }}</span></div>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control text-right" value="{{ old('amount', $expense->amount) }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="required">Payment method</label>
                            <select name="payment_method" class="form-control custom-select" required>
                                @foreach (payment_methods() as $k => $v)
                                    <option value="{{ $k }}" @selected(old('payment_method', $expense->payment_method) === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Paid to (payee)</label>
                            <input type="text" name="payee" class="form-control" value="{{ old('payee', $expense->payee) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Payment reference</label>
                            <input type="text" name="payment_reference" class="form-control" value="{{ old('payment_reference', $expense->payment_reference) }}" placeholder="Receipt / transaction code / cheque no.">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group mb-0">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $expense->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                @unless ($expense->exists)
                    <button type="submit" name="submit_action" value="add_another" class="btn btn-default"><i class="fas fa-plus"></i> Save &amp; add another</button>
                @endunless
                <button type="submit" name="submit_action" value="save" class="btn btn-primary"><i class="fas fa-save"></i> Save expense</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Newly added category (from the modal) becomes selectable immediately
    $(document).on('app:saved', function (e, res) {
        if (res.category) {
            $('#expense_category_id').append(new Option(res.category.text, res.category.id, true, true)).trigger('change');
        }
    });
</script>
@endpush
