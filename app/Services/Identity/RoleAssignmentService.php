<?php

namespace App\Services\Identity;

use App\Exceptions\Identity\RoleAssignmentConflict;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Role;

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
            $this->assertTransitionAllowed($user, $key, $oldValues, $newValues);

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

    /**
     * @param  list<string>  $oldValues
     * @param  list<string>  $newValues
     */
    private function assertTransitionAllowed(
        User $user,
        string $key,
        array $oldValues,
        array $newValues,
    ): void {
        $oldRoles = $key === 'roles' ? $oldValues : $this->roleNames($user);
        $newRoles = $key === 'roles' ? $newValues : $oldRoles;
        $oldPermissions = $key === 'permissions' ? $oldValues : $this->directPermissionNames($user);
        $newPermissions = $key === 'permissions' ? $newValues : $oldPermissions;

        $businessPermissions = $this->businessPermissions();
        $oldBusinessPermissions = $this->effectiveBusinessPermissions(
            $oldRoles,
            $oldPermissions,
            $businessPermissions,
        );
        $newBusinessPermissions = $this->effectiveBusinessPermissions(
            $newRoles,
            $newPermissions,
            $businessPermissions,
        );

        if (in_array('super_admin', $newRoles, true) && $newBusinessPermissions !== []) {
            $isStrictRemediation = in_array('super_admin', $oldRoles, true)
                && $oldBusinessPermissions !== []
                && array_diff($newBusinessPermissions, $oldBusinessPermissions) === []
                && count($newBusinessPermissions) < count($oldBusinessPermissions);

            if (! $isStrictRemediation) {
                throw RoleAssignmentConflict::superAdminBusinessConflict();
            }
        }

        if (
            $key === 'roles'
            && in_array('direction', $newRoles, true)
            && ! in_array('direction', $oldRoles, true)
        ) {
            $this->assertDirectionSlotAvailable($user);
        }
    }

    private function assertDirectionSlotAvailable(User $user): void
    {
        $guard = $this->guardName();
        $directionRole = Role::query()
            ->where('name', 'direction')
            ->where('guard_name', $guard)
            ->lockForUpdate()
            ->first();

        if (! $directionRole instanceof Role) {
            throw new LogicException('Le rôle direction est absent du catalogue RBAC en base.');
        }

        $directionAccounts = User::role('direction')
            ->whereKeyNot($user->getKey())
            ->count();

        if ($directionAccounts >= 2) {
            throw RoleAssignmentConflict::directionLimitReached();
        }
    }

    /** @return list<string> */
    private function businessPermissions(): array
    {
        $roles = config('permission-catalog.roles');
        $permissions = config('permission-catalog.permissions');

        if (
            ! is_array($roles)
            || ! is_array($permissions)
            || ! isset($roles['super_admin'])
            || ! is_array($roles['super_admin'])
        ) {
            throw new LogicException('Le catalogue RBAC est invalide.');
        }

        $technicalPermissions = array_values(array_filter(
            $roles['super_admin'],
            static fn (mixed $permission): bool => is_string($permission),
        ));
        $catalogPermissions = array_values(array_filter(
            $permissions,
            static fn (mixed $permission): bool => is_string($permission),
        ));

        return array_values(array_diff($catalogPermissions, $technicalPermissions));
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $directPermissions
     * @param  list<string>  $businessPermissions
     * @return list<string>
     */
    private function effectiveBusinessPermissions(
        array $roles,
        array $directPermissions,
        array $businessPermissions,
    ): array {
        $rolePermissions = $roles === []
            ? []
            : Role::query()
                ->where('guard_name', $this->guardName())
                ->whereIn('name', $roles)
                ->with('permissions:id,name')
                ->get()
                ->flatMap(static fn (Role $role) => $role->permissions->pluck('name'))
                ->map(static fn (mixed $permission): string => (string) $permission)
                ->all();

        return $this->normalizeNames(array_values(array_intersect(
            [...$rolePermissions, ...$directPermissions],
            $businessPermissions,
        )));
    }

    private function guardName(): string
    {
        $guard = config('permission-catalog.guard');

        if (! is_string($guard) || $guard === '') {
            throw new LogicException('Le guard du catalogue RBAC est invalide.');
        }

        return $guard;
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
