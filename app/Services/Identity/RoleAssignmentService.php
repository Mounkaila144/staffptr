<?php

namespace App\Services\Identity;

use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Closure;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function assignRole(
        User $user,
        string $role,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): User {
        $roles = $this->roleNames($user);
        $newRoles = $this->normalizeNames([...$roles, $role]);

        return $this->changeAssignments(
            user: $user,
            key: 'roles',
            oldValues: $roles,
            newValues: $newRoles,
            action: 'user_role_assigned',
            operation: fn (): User => $user->assignRole($role),
            actorId: $actorId,
            actorLabel: $actorLabel,
            reason: $reason,
        );
    }

    /** @param list<string> $roles */
    public function syncRoles(
        User $user,
        array $roles,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): User {
        $oldRoles = $this->roleNames($user);
        $newRoles = $this->normalizeNames($roles);

        return $this->changeAssignments(
            user: $user,
            key: 'roles',
            oldValues: $oldRoles,
            newValues: $newRoles,
            action: 'user_roles_changed',
            operation: fn (): User => $user->syncRoles($newRoles),
            actorId: $actorId,
            actorLabel: $actorLabel,
            reason: $reason,
        );
    }

    public function removeRole(
        User $user,
        string $role,
        ?int $actorId,
        string $actorLabel,
        string $reason,
    ): User {
        $roles = $this->roleNames($user);
        $newRoles = array_values(array_diff($roles, [$role]));

        return $this->changeAssignments(
            user: $user,
            key: 'roles',
            oldValues: $roles,
            newValues: $newRoles,
            action: 'user_role_removed',
            operation: fn (): User => $user->removeRole($role),
            actorId: $actorId,
            actorLabel: $actorLabel,
            reason: $reason,
        );
    }

    public function grantPermission(
        User $user,
        string $permission,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): User {
        $permissions = $this->directPermissionNames($user);
        $newPermissions = $this->normalizeNames([...$permissions, $permission]);

        return $this->changeAssignments(
            user: $user,
            key: 'permissions',
            oldValues: $permissions,
            newValues: $newPermissions,
            action: 'user_permission_granted',
            operation: fn (): User => $user->givePermissionTo($permission),
            actorId: $actorId,
            actorLabel: $actorLabel,
            reason: $reason,
        );
    }

    /** @param list<string> $permissions */
    public function syncPermissions(
        User $user,
        array $permissions,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): User {
        $oldPermissions = $this->directPermissionNames($user);
        $newPermissions = $this->normalizeNames($permissions);

        return $this->changeAssignments(
            user: $user,
            key: 'permissions',
            oldValues: $oldPermissions,
            newValues: $newPermissions,
            action: 'user_permissions_changed',
            operation: fn (): User => $user->syncPermissions($newPermissions),
            actorId: $actorId,
            actorLabel: $actorLabel,
            reason: $reason,
        );
    }

    public function revokePermission(
        User $user,
        string $permission,
        ?int $actorId,
        string $actorLabel,
        string $reason,
    ): User {
        $permissions = $this->directPermissionNames($user);
        $newPermissions = array_values(array_diff($permissions, [$permission]));

        return $this->changeAssignments(
            user: $user,
            key: 'permissions',
            oldValues: $permissions,
            newValues: $newPermissions,
            action: 'user_permission_revoked',
            operation: fn (): User => $user->revokePermissionTo($permission),
            actorId: $actorId,
            actorLabel: $actorLabel,
            reason: $reason,
        );
    }

    /**
     * @param  list<string>  $oldValues
     * @param  list<string>  $newValues
     * @param  Closure(): User  $operation
     */
    private function changeAssignments(
        User $user,
        string $key,
        array $oldValues,
        array $newValues,
        string $action,
        Closure $operation,
        ?int $actorId,
        string $actorLabel,
        ?string $reason,
    ): User {
        if ($oldValues === $newValues) {
            return $user;
        }

        return DB::connection($user->getConnectionName())->transaction(function () use (
            $user,
            $key,
            $oldValues,
            $newValues,
            $action,
            $operation,
            $actorId,
            $actorLabel,
            $reason,
        ): User {
            $this->auditLogger->runExplicitly(
                auditable: $user,
                operation: $operation,
                actorId: $actorId,
                actorLabel: $actorLabel,
                action: $action,
                oldValues: [$key => $oldValues],
                newValues: [$key => $newValues],
                reason: $reason,
            );

            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');

            return $user;
        });
    }

    /** @return list<string> */
    private function roleNames(User $user): array
    {
        return $user->getRoleNames()->sort()->values()->all();
    }

    /** @return list<string> */
    private function directPermissionNames(User $user): array
    {
        return $user->getDirectPermissions()
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function normalizeNames(array $names): array
    {
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
