<div class="modal-dialog" role="document">
    <form action="{{ $unit->exists ? route('units.update', $unit) : route('units.store') }}" method="POST" class="modal-content ajax-form">
        @csrf
        @if ($unit->exists) @method('PUT') @endif
        <div class="modal-header">
            <h5 class="modal-title">{{ $unit->exists ? 'Edit Unit' : 'Add Unit' }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group col-md-7">
                    <label class="required">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $unit->name }}" required placeholder="e.g. Kilogram">
                </div>
                <div class="form-group col-md-5">
                    <label class="required">Short name</label>
                    <input type="text" name="short_name" class="form-control" value="{{ $unit->short_name }}" required placeholder="e.g. Kg">
                </div>
            </div>
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="allow_decimal" name="allow_decimal" value="1" @checked($unit->allow_decimal)>
                <label class="custom-control-label" for="allow_decimal">Allow decimal quantities</label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
