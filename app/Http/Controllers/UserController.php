<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return static::crudPermissions('users');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(User::query()->with('roles'))
                ->editColumn('username', fn ($u) => '<code>'.e($u->username).'</code>')
                ->editColumn('name', fn ($u) => e($u->name).($u->id === auth()->id() ? ' <span class="badge badge-primary">You</span>' : ''))
                ->addColumn('role', fn ($u) => $u->roles->map(fn ($r) => '<span class="badge badge-info">'.e($r->name).'</span>')->implode(' '))
                ->editColumn('is_active', fn ($u) => status_badge($u->is_active ? 'active' : 'inactive'))
                ->editColumn('last_login_at', fn ($u) => $u->last_login_at ? format_date($u->last_login_at, true) : '<span class="text-muted">Never</span>')
                ->addColumn('action', fn ($u) => $this->actions([
                    ['label' => 'Edit', 'icon' => 'fas fa-edit', 'modal' => route('users.edit', $u), 'can' => 'users.edit'],
                    $u->id === auth()->id() ? '-' : ['label' => 'Delete', 'delete' => route('users.destroy', $u), 'can' => 'users.delete'],
                ]))
                ->filterColumn('role', fn ($q, $k) => $q->whereHas('roles', fn ($r) => $r->where('name', 'like', "%{$k}%")))
                ->rawColumns(['name', 'username', 'role', 'is_active', 'last_login_at', 'action'])
                ->make(true);
        }

        return view('users.index');
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->assignableRoles()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        $user->syncRoles([$request->role]);

        return $this->success("User {$user->name} created");
    }

    public function edit(User $user)
    {
        $this->guardSuperUser($user);

        return view('users.form', ['user' => $user, 'roles' => $this->assignableRoles()]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardSuperUser($user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->id === auth()->id()) {
            $data['is_active'] = true; // cannot lock yourself out
        }
        $user->update($data);

        if ($user->id !== auth()->id() || $user->hasRole($request->role)) {
            $user->syncRoles([$request->role]);
        }

        return $this->success("User {$user->name} updated");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return $this->failure('You cannot delete your own account.');
        }
        $this->guardSuperUser($user);
        $superRoles = config('pos.super_roles', ['Admin']);
        if ($user->hasAnyRole($superRoles) && User::role($superRoles)->where('id', '!=', $user->id)->where('is_active', true)->doesntExist()) {
            return $this->failure('At least one administrator must remain.');
        }
        $user->delete();

        return $this->success("User {$user->name} deleted");
    }

    protected function isSuper(?User $user = null): bool
    {
        return ($user ?? auth()->user())->hasAnyRole(config('pos.super_roles', ['Admin']));
    }

    /** Only administrators may grant administrator roles. */
    protected function assignableRoles()
    {
        $roles = Role::orderBy('name')->pluck('name');

        return $this->isSuper() ? $roles : $roles->reject(fn ($r) => in_array($r, config('pos.super_roles', ['Admin']), true))->values();
    }

    /** Non-administrators may not edit, reset or delete administrator accounts. */
    protected function guardSuperUser(User $target): void
    {
        abort_if($this->isSuper($target) && ! $this->isSuper(), 403, 'Only an administrator can modify an administrator account.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->assignableRoles()->all())],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['role']);

        return $data;
    }
}
