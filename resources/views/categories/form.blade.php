<div class="modal-dialog" role="document">
    <form action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}" method="POST" class="modal-content ajax-form">
        @csrf
        @if ($category->exists) @method('PUT') @endif
        <div class="modal-header">
            <h5 class="modal-title">{{ $category->exists ? 'Edit Category' : 'Add Category' }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="required" for="name">Category name</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ $category->name }}" required maxlength="120">
            </div>
            <div class="form-group">
                <label for="code">Code</label>
                <input type="text" name="code" id="code" class="form-control" value="{{ $category->code }}" maxlength="30">
            </div>
            <div class="form-group mb-0">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3" class="form-control">{{ $category->description }}</textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
