<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('permission-catalog.guard');
        $roles = config('permission-catalog.roles');
        $permissions = config('permission-catalog.permissions');

        if (! is_string($guard) || ! is_array($roles) || ! is_array($permissions)) {
            throw new LogicException('Le catalogue RBAC est invalide.');
        }

        DB::transaction(function () use ($guard, $roles, $permissions): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($permissions as $permissionName) {
                if (! is_string($permissionName)) {
                    throw new LogicException('Chaque permission du catalogue doit être une chaîne.');
                }

                Permission::query()->updateOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                ]);
            }

            foreach ($roles as $roleName => $permissionNames) {
                if (! is_string($roleName) || ! is_array($permissionNames)) {
                    throw new LogicException('Chaque rôle doit déclarer une liste de permissions.');
                }

                $role = Role::query()->updateOrCreate([
                    'name' => $roleName,
                    'guard_name' => $guard,
                ]);
                $unexpectedPermissions = $role->permissions()
                    ->whereNotIn('name', $permissionNames)
                    ->pluck('name');

                if ($unexpectedPermissions->isNotEmpty()) {
                    throw new LogicException(
                        "Le rôle {$roleName} porte des permissions hors catalogue : ".
                        $unexpectedPermissions->implode(', ')
                    );
                }

                $role->givePermissionTo($permissionNames);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
