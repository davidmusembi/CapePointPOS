<div class="modal-dialog" role="document">
    <form action="{{ $taxRate->exists ? route('tax-rates.update', $taxRate) : route('tax-rates.store') }}" method="POST" class="modal-content ajax-form">
        @csrf
        @if ($taxRate->exists) @method('PUT') @endif
        <div class="modal-header">
            <h5 class="modal-title">{{ $taxRate->exists ? 'Edit Tax Rate' : 'Add Tax Rate' }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group col-md-7">
                    <label class="required">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $taxRate->name }}" required placeholder="e.g. VAT">
                </div>
                <div class="form-group col-md-5">
                    <label class="required">Rate (%)</label>
                    <input type="number" step="any" min="0" max="100" name="rate" class="form-control" value="{{ $taxRate->exists ? (float) $taxRate->rate : '' }}" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
