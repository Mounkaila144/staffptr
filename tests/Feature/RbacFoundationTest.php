<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\IdentityTestCase;

class RbacFoundationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_1_multiple_roles_and_direct_permissions_form_an_immediate_union(): void
    {
        $user = User::factory()->create();
        $service = app(RoleAssignmentService::class);

        $service->assignRole($user, 'tuteur', null, 'Test RBAC');
        $service->assignRole($user, 'finance', null, 'Test RBAC');
        $service->grantPermission($user, 'parametre.gerer', null, 'Test RBAC');

        $this->assertTrue($user->can('depense.creer'));
        $this->assertTrue($user->can('depense.payer'));
        $this->assertTrue($user->can('parametre.gerer'));
        $this->assertEqualsCanonicalizing(['finance', 'tuteur'], $user->getRoleNames()->all());
    }

    public function test_ac_1_rbac_schema_has_five_distinct_tables_without_soft_deletes(): void
    {
        foreach (['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertFalse(Schema::hasColumn($table, 'deleted_at'));
        }
    }

    public function test_ac_1_role_removal_preserves_permissions_from_other_sources(): void
    {
        $user = User::factory()->create();
        $service = app(RoleAssignmentService::class);

        $service->syncRoles($user, ['tuteur', 'finance'], null, 'Test RBAC');
        $service->grantPermission($user, 'parametre.gerer', null, 'Test RBAC');
        $service->removeRole($user, 'finance', null, 'Test RBAC', 'Changement de fonction');

        $this->assertFalse($user->fresh()->can('depense.payer'));
        $this->assertTrue($user->fresh()->can('depense.creer'));
        $this->assertTrue($user->fresh()->can('parametre.gerer'));
    }

    public function test_ac_2_catalog_contains_exactly_the_six_roles_and_is_idempotent(): void
    {
        $expectedRoles = ['super_admin', 'direction', 'finance', 'tuteur', 'employe', 'stagiaire'];
        $roleCount = Role::query()->count();
        $permissionCount = Permission::query()->count();

        $this->seedRbac();

        $this->assertEqualsCanonicalizing($expectedRoles, Role::query()->pluck('name')->all());
        $this->assertSame($roleCount, Role::query()->count());
        $this->assertSame($permissionCount, Permission::query()->count());
        $this->assertFalse(Role::query()->where('name', 'auditeur')->exists());
        $this->assertFalse(Permission::query()->where('name', 'approuver_depense')->exists());
    }

    public function test_ac_2_every_catalog_permission_is_granted_and_declared_once(): void
    {
        $declared = config('permission-catalog.permissions');

        $this->assertIsArray($declared);
        $this->assertCount(count(array_unique($declared)), $declared);
        $this->assertEqualsCanonicalizing($declared, Permission::query()->pluck('name')->all());

        foreach (Permission::query()->get() as $permission) {
            $this->assertTrue(
                $permission->roles()->exists(),
                "La permission {$permission->name} n'est accordée par la matrice à aucun rôle.",
            );
        }
    }

    public function test_ac_4_expense_approval_belongs_to_direction_role_only(): void
    {
        $permission = Permission::findByName('depense.approuver');

        $this->assertSame(['direction'], $permission->roles()->pluck('name')->sort()->values()->all());
    }

    public function test_ac_3_super_admin_catalog_excludes_all_four_business_powers(): void
    {
        $superAdmin = Role::findByName('super_admin');
        $forbidden = [
            'depense.approuver',
            'objectif.valider',
            'rapport_financier.valider',
            'audit.consulter',
        ];

        $this->assertSame([], $superAdmin->permissions()->whereIn('name', $forbidden)->pluck('name')->all());
    }

    public function test_ac_1_shared_inertia_permissions_match_the_composable_contract_without_stale_revocation(): void
    {
        $user = User::factory()->active()->create();
        $service = app(RoleAssignmentService::class);
        $service->assignRole($user, 'finance', null, 'Test RBAC');
        $service->grantPermission($user, 'parametre.gerer', null, 'Test RBAC');
        $request = Request::create('/');
        $request->setUserResolver(static fn (): User => $user);
        $middleware = app(HandleInertiaRequests::class);

        $permissions = $middleware->share($request)['auth']['permissions'];

        $this->assertContains('role:finance', $permissions);
        $this->assertContains('depense.payer', $permissions);
        $this->assertContains('parametre.gerer', $permissions);

        $service->removeRole($user, 'finance', null, 'Test RBAC', 'Révocation immédiate');
        $service->revokePermission($user, 'parametre.gerer', null, 'Test RBAC', 'Révocation immédiate');
        $permissionsAfterRevocation = $middleware->share($request)['auth']['permissions'];

        $this->assertNotContains('role:finance', $permissionsAfterRevocation);
        $this->assertNotContains('depense.payer', $permissionsAfterRevocation);
        $this->assertNotContains('parametre.gerer', $permissionsAfterRevocation);
    }

    public function test_ac_7_read_only_role_requires_data_only_and_cannot_write(): void
    {
        $tablesBefore = Schema::getTableListing();
        $role = Role::create(['name' => 'lecture_seule_temporaire', 'guard_name' => 'web']);
        $role->givePermissionTo(['compte.consulter', 'document_interne.consulter']);
        $user = User::factory()->create();

        app(RoleAssignmentService::class)->assignRole($user, $role->name, null, 'Test RBAC');

        $this->assertTrue($user->can('compte.consulter'));
        $this->assertTrue($user->can('document_interne.consulter'));
        $this->assertFalse($user->can('compte.gerer'));
        $this->assertFalse($user->can('document_interne.gerer'));
        $this->assertFalse($user->can('depense.creer'));
        $this->assertSame($tablesBefore, Schema::getTableListing());
    }
}
