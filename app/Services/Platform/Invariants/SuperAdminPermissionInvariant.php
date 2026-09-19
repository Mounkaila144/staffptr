<?php

namespace App\Services\Platform\Invariants;

use App\Models\Identity\User;
use Spatie\Permission\Models\Role;

class SuperAdminPermissionInvariant implements InvariantCheck
{
    private const NAME = 'Permissions métier du super administrateur';

    public function check(): InvariantResult
    {
        $roles = config('permission-catalog.roles');
        $permissions = config('permission-catalog.permissions');

        if (! is_array($roles) || ! is_array($permissions) || ! isset($roles['super_admin']) || ! is_array($roles['super_admin'])) {
            return InvariantResult::fail(
                self::NAME,
                'catalogue de permissions illisible',
                'catalogue RBAC chargé et cohérent',
            );
        }

        $technicalPermissions = array_values(array_filter(
            $roles['super_admin'],
            static fn (mixed $permission): bool => is_string($permission),
        ));
        $allPermissions = array_values(array_filter(
            $permissions,
            static fn (mixed $permission): bool => is_string($permission),
        ));
        $businessPermissions = array_values(array_diff($allPermissions, $technicalPermissions));
        $superAdminRole = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->first();

        if (! $superAdminRole instanceof Role) {
            return InvariantResult::fail(
                self::NAME,
                'rôle super_admin absent',
                'rôle super_admin présent sans permission métier',
            );
        }

        $violations = [];
        $roleBusinessPermissions = array_values(array_intersect(
            $superAdminRole->getPermissionNames()->all(),
            $businessPermissions,
        ));

        if ($roleBusinessPermissions !== []) {
            $violations[] = 'rôle : '.implode(', ', $roleBusinessPermissions);
        }

        User::role('super_admin')->get()->each(function (User $user) use (&$violations, $businessPermissions): void {
            $effectiveBusinessPermissions = array_values(array_intersect(
                $user->getAllPermissions()->pluck('name')->all(),
                $businessPermissions,
            ));

            if ($effectiveBusinessPermissions !== []) {
                $violations[] = "compte #{$user->getKey()} : ".implode(', ', $effectiveBusinessPermissions);
            }
        });

        return $violations === []
            ? InvariantResult::pass(self::NAME, 'aucune permission métier détectée', 'aucune permission métier')
            : InvariantResult::fail(self::NAME, implode(' ; ', $violations), 'aucune permission métier');
    }
}
