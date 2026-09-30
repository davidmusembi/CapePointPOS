<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->orderBy('name')->get();

        return view('roles.index', ['roles' => $roles, 'totalPermissions' => Permission::count()]);
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role, 'granted' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        $this->flush();

        return redirect()->route('roles.index')->with('success', "Role {$role->name} created");
    }

    public function edit(Role $role)
    {
        return view('roles.form', ['role' => $role, 'granted' => $role->permissions->pluck('name')->all()]);
    }

    public function update(Request $request, Role $role)
    {
        // A non-administrator must not be able to widen the permissions of a role they hold.
        abort_if(! auth()->user()->hasAnyRole(config('pos.super_roles', ['Admin'])) && auth()->user()->hasRole($role->name), 403, 'You cannot change the permissions of your own role.');

        $data = $this->validated($request, $role);
        if (! $this->isProtected($role)) {
            $role->update(['name' => $data['name']]);
        }
        $role->syncPermissions($data['permissions'] ?? []);
        $this->flush();

        return redirect()->route('roles.index')->with('success', "Role {$role->name} updated");
    }

    public function destroy(Role $role)
    {
        if ($this->isProtected($role)) {
            return $this->failure('The Admin role cannot be deleted.');
        }
        if ($role->users()->exists()) {
            return $this->failure('This role is assigned to users. Reassign them first.');
        }
        $role->delete();
        $this->flush();

        return $this->success("Role {$role->name} deleted");
    }

    protected function isProtected(Role $role): bool
    {
        return in_array($role->name, config('pos.super_roles', ['Admin']), true);
    }

    protected function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);
    }

    protected function flush(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
