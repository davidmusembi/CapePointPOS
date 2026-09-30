<div class="modal-dialog" role="document">
    <form action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" method="POST" class="modal-content ajax-form">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <div class="modal-header">
            <h5 class="modal-title">{{ $user->exists ? 'Edit User' : 'Add User' }}</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="required">Full name</label>
                <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label class="required">Username (login)</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                        <input type="text" name="username" class="form-control" value="{{ $user->username }}" required maxlength="50"
                               pattern="[A-Za-z0-9._\-]{3,50}" title="3-50 characters: letters, numbers, dot, dash or underscore" autocapitalize="none" spellcheck="false">
                    </div>
                    <small class="text-muted">Letters, numbers, dot, dash or underscore.</small>
                </div>
                <div class="form-group col-md-6">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ $user->phone }}">
                </div>
                <div class="form-group col-md-12">
                    <label class="required">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                </div>
            </div>
            <div class="form-group">
                <label class="required">Role</label>
                <select name="role" class="form-control custom-select" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected($user->exists && $user->hasRole($role))>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label class="{{ $user->exists ? '' : 'required' }}">Password</label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password" {{ $user->exists ? '' : 'required' }} placeholder="{{ $user->exists ? 'Leave blank to keep' : 'Min. 8 characters' }}">
                </div>
                <div class="form-group col-md-6">
                    <label>Confirm password</label>
                    <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
            </div>
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="user_active" name="is_active" value="1" @checked($user->is_active) @disabled($user->id === auth()->id())>
                <label class="custom-control-label" for="user_active">Active (can sign in)</label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </form>
</div>
