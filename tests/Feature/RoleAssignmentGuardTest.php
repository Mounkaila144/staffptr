<?php

namespace Tests\Feature;

use App\Exceptions\Identity\RoleAssignmentConflict;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\RoleAssignmentService;
use Tests\Support\IdentityTestCase;

class RoleAssignmentGuardTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_business_role_cannot_be_added_to_a_super_admin(): void
    {
        $user = User::factory()->create();
        $assignments = app(RoleAssignmentService::class);
        $assignments->assignRole($user, 'super_admin', null, 'Test');

        $this->expectException(RoleAssignmentConflict::class);
        $this->expectExceptionMessage('aucun rôle ni permission métier');

        $assignments->assignRole($user, 'direction', null, 'Test');
    }

    public function test_super_admin_cannot_be_added_to_an_account_with_a_business_role(): void
    {
        $user = User::factory()->create();
        $assignments = app(RoleAssignmentService::class);
        $assignments->assignRole($user, 'employe', null, 'Test');

        try {
            $assignments->assignRole($user, 'super_admin', null, 'Test');
            $this->fail("L'affectation super_admin devait être refusée.");
        } catch (RoleAssignmentConflict) {
            $this->assertFalse($user->fresh()->hasRole('super_admin'));
            $this->assertTrue($user->fresh()->hasRole('employe'));
        }
    }

    public function test_business_direct_permission_cannot_be_granted_to_a_super_admin(): void
    {
        $user = User::factory()->create();
        $assignments = app(RoleAssignmentService::class);
        $assignments->assignRole($user, 'super_admin', null, 'Test');

        $this->expectException(RoleAssignmentConflict::class);

        $assignments->grantPermission($user, 'depense.approuver', null, 'Test');
    }

    public function test_super_admin_actor_can_assign_direction_to_another_account(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $assignments = app(RoleAssignmentService::class);
        $assignments->assignRole($actor, 'super_admin', null, 'Test');

        $assignments->assignRole($target, 'direction', $actor->getKey(), 'Super administrateur');

        $this->assertTrue($target->fresh()->hasRole('direction'));
    }

    public function test_a_third_direction_account_is_refused_without_audit_or_assignment(): void
    {
        $assignments = app(RoleAssignmentService::class);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        $assignments->assignRole($first, 'direction', null, 'Test');
        $assignments->assignRole($second, 'direction', null, 'Test');

        try {
            $assignments->assignRole($third, 'direction', null, 'Test');
            $this->fail('Le troisième compte direction devait être refusé.');
        } catch (RoleAssignmentConflict $exception) {
            $this->assertStringContainsString('Deux comptes direction', $exception->getMessage());
        }

        $this->assertFalse($third->fresh()->hasRole('direction'));
        $this->assertSame(2, AuditLog::query()->where('action', 'user_role_assigned')->count());
    }
}
