<div class="modal-dialog" role="document">
    <form action="{{ $category->exists ? route('expense-categories.update', $category) : route('expense-categories.store') }}" method="POST" class="modal-content ajax-form">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <div class="modal-header">
            <h5 class="modal-title">{{ $category->exists ? 'Edit Expense Category' : 'Add Expense Category' }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label class="required">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $category->name }}" required>
                </div>
                <div class="form-group col-md-4">
                    <label>Code</label>
                    <input type="text" name="code" class="form-control" value="{{ $category->code }}">
                </div>
            </div>
            <div class="form-group mb-0">
                <label>Description</label>
                <textarea name="description" rows="2" class="form-control">{{ $category->description }}</textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
