<div class="modal-dialog modal-lg" role="document">
    <form action="{{ $contact->exists ? route($meta['route'].'.update', $contact) : route($meta['route'].'.store') }}" method="POST" class="modal-content ajax-form" id="contact_form">
        @csrf
        @if ($contact->exists) @method('PUT') @endif
        <div class="modal-header">
            <h5 class="modal-title">{{ $contact->exists ? 'Edit '.$meta['singular'] : 'Add '.$meta['singular'] }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="required">Contact name</label>
                        <input type="text" name="name" class="form-control" value="{{ $contact->name }}" required maxlength="190">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Business / company name</label>
                        <input type="text" name="company" class="form-control" value="{{ $contact->company }}" maxlength="190">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{{ $meta['singular'] }} code</label>
                        <input type="text" name="code" class="form-control" value="{{ $contact->code }}" placeholder="Auto-generated">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Mobile / phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ $contact->phone }}" maxlength="50">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="{{ $contact->email }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" class="form-control" value="{{ $contact->address }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" class="form-control" value="{{ $contact->city }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tax number / KRA PIN</label>
                        <input type="text" name="tax_number" class="form-control" value="{{ $contact->tax_number }}">
                    </div>
                </div>
            </div>

            <div class="form-section-title">Account</div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Pay term (days)</label>
                        <input type="number" min="0" max="365" name="payment_terms" class="form-control" value="{{ $contact->payment_terms }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Opening balance</label>
                        <input type="number" step="any" name="opening_balance" class="form-control" value="{{ (float) $contact->opening_balance }}">
                        <small class="text-muted">{{ $meta['type'] === 'customer' ? 'Amount the customer owed you when added.' : 'Amount you owed the supplier when added.' }}</small>
                    </div>
                </div>
                @if ($meta['type'] === 'customer')
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Credit limit</label>
                            <input type="number" step="any" min="0" name="credit_limit" class="form-control" value="{{ $contact->credit_limit }}" placeholder="No limit">
                        </div>
                    </div>
                @endif
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="2" class="form-control">{{ $contact->notes }}</textarea>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="contact_active" name="is_active" value="1" @checked($contact->is_active)>
                        <label class="custom-control-label" for="contact_active">Active</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
