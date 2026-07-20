<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\IdentityTestCase;

class RolePermissionSeederIdempotenceTest extends IdentityTestCase
{
    public function test_ac_1_reference_catalog_is_idempotent_when_seeded_twice(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $firstCounts = $this->catalogCounts();

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($firstCounts, $this->catalogCounts());
        $this->assertSame(6, $firstCounts['roles']);
    }

    public function test_ac_1_existing_assignments_and_out_of_catalog_entries_survive_reseeding(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole(
            $user,
            'employe',
            null,
            'Test de rejouabilité',
        );
        $customRole = Role::create(['name' => 'lecture_seule_locale', 'guard_name' => 'web']);
        $customPermission = Permission::create(['name' => 'donnee_locale.consulter', 'guard_name' => 'web']);
        $customRole->givePermissionTo($customPermission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RolePermissionSeeder::class);

        $user->refresh();
        $this->assertTrue($user->hasRole('employe'));
        $this->assertTrue($user->can('depense.creer'));
        $this->assertDatabaseHas('roles', ['name' => 'lecture_seule_locale', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'donnee_locale.consulter', 'guard_name' => 'web']);
        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $customRole->getKey(),
            'permission_id' => $customPermission->getKey(),
        ]);
    }

    /** @return array{roles: int, permissions: int, role_permissions: int} */
    private function catalogCounts(): array
    {
        return [
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
            'role_permissions' => DB::table('role_has_permissions')->count(),
        ];
    }
}
