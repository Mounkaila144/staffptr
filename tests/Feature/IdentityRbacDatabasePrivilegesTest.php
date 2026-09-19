<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\IdentityTestCase;

class IdentityRbacDatabasePrivilegesTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_6_application_account_can_revoke_only_user_assignment_pivots(): void
    {
        $this->requireMysqlProof();

        $user = User::factory()->active()->create();
        $service = app(RoleAssignmentService::class);

        $service->assignRole($user, 'employe', null, 'Test privilèges');
        $service->grantPermission($user, 'compte.consulter', null, 'Test privilèges');
        $service->removeRole($user, 'employe', null, 'Test privilèges', 'Retrait contrôlé');
        $service->revokePermission(
            $user,
            'compte.consulter',
            null,
            'Test privilèges',
            'Retrait contrôlé',
        );

        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $user->getKey()]);
        $this->assertDatabaseMissing('model_has_permissions', ['model_id' => $user->getKey()]);
    }

    public function test_task_0_delete_remains_refused_on_rbac_catalog_tables(): void
    {
        $this->requireMysqlProof();

        $role = Role::create(['name' => 'catalogue_protege', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'catalogue.protege', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        foreach ([
            ['roles', ['id' => $role->getKey()]],
            ['permissions', ['id' => $permission->getKey()]],
            ['role_has_permissions', [
                'role_id' => $role->getKey(),
                'permission_id' => $permission->getKey(),
            ]],
        ] as [$table, $criteria]) {
            try {
                DB::table($table)->where($criteria)->delete();
                $this->fail("DELETE devait rester refusé sur {$table}.");
            } catch (QueryException $exception) {
                $this->assertSame(1142, $exception->errorInfo[1] ?? null);
            }
        }

        $this->assertDatabaseHas('roles', ['id' => $role->getKey()]);
        $this->assertDatabaseHas('permissions', ['id' => $permission->getKey()]);
        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->getKey(),
            'permission_id' => $permission->getKey(),
        ]);
    }
}
