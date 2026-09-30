<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(config('pos.permissions'))->flatMap(fn ($group) => array_keys($group));
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('pos.roles') as $roleName => $patterns) {
            $role = Role::findOrCreate($roleName, 'web');
            $exclude = collect($patterns)->filter(fn ($p) => str_starts_with($p, '!'))->map(fn ($p) => substr($p, 1));
            $granted = $all->filter(function ($perm) use ($patterns, $exclude) {
                if ($exclude->contains(fn ($p) => Str::is($p, $perm))) {
                    return false;
                }
                foreach ($patterns as $pattern) {
                    if (Str::is($pattern, $perm)) {
                        return true;
                    }
                }

                return false;
            });
            $role->syncPermissions($granted->values()->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
