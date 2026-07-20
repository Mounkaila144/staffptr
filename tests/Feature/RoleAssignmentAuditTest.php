<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\RoleAssignmentService;
use App\Support\Auditing\AuditLogger;
use Closure;
use Mockery;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class RoleAssignmentAuditTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_6_role_assignment_modification_and_removal_are_audited_with_both_values(): void
    {
        $user = User::factory()->create();
        $service = app(RoleAssignmentService::class);

        $service->assignRole($user, 'employe', null, 'Direction test');
        $service->syncRoles($user, ['employe', 'tuteur'], null, 'Direction test');
        $service->removeRole($user, 'employe', null, 'Direction test', 'Nouvelle responsabilité');

        $this->assertAuditValues('user_role_assigned', [], ['employe']);
        $this->assertAuditValues('user_roles_changed', ['employe'], ['employe', 'tuteur']);
        $this->assertAuditValues('user_role_removed', ['employe', 'tuteur'], ['tuteur']);
    }

    public function test_ac_6_direct_permission_grant_modification_and_revocation_are_audited(): void
    {
        $user = User::factory()->create();
        $service = app(RoleAssignmentService::class);

        $service->grantPermission($user, 'document_interne.consulter', null, 'Direction test');
        $service->syncPermissions(
            $user,
            ['document_interne.consulter', 'compte.consulter'],
            null,
            'Direction test',
        );
        $service->revokePermission(
            $user,
            'document_interne.consulter',
            null,
            'Direction test',
            'Fin du besoin de consultation',
        );

        $this->assertAuditValues('user_permission_granted', [], ['document_interne.consulter'], 'permissions');
        $this->assertAuditValues(
            'user_permissions_changed',
            ['document_interne.consulter'],
            ['compte.consulter', 'document_interne.consulter'],
            'permissions',
        );
        $this->assertAuditValues(
            'user_permission_revoked',
            ['compte.consulter', 'document_interne.consulter'],
            ['compte.consulter'],
            'permissions',
        );
    }

    public function test_ac_6_audit_failure_rolls_back_the_pivot_assignment(): void
    {
        $user = User::factory()->create();
        $logger = Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('runExplicitly')
            ->once()
            ->andReturnUsing(function (User $_auditable, Closure $operation): never {
                $operation();

                throw new RuntimeException("L'audit est indisponible.");
            });

        $service = new RoleAssignmentService($logger);

        try {
            $service->assignRole($user, 'direction', null, 'Direction test');
            $this->fail("L'échec d'audit devait annuler l'affectation.");
        } catch (RuntimeException $exception) {
            $this->assertSame("L'audit est indisponible.", $exception->getMessage());
        }

        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => User::class,
            'model_id' => $user->getKey(),
        ]);
        $this->assertFalse($user->fresh()->hasRole('direction'));
    }

    /**
     * @param  list<string>  $oldValues
     * @param  list<string>  $newValues
     */
    private function assertAuditValues(
        string $action,
        array $oldValues,
        array $newValues,
        string $key = 'roles',
    ): void {
        $audit = AuditLog::query()->where('action', $action)->sole();

        $this->assertSame([$key => $oldValues], $audit->old_values);
        $this->assertSame([$key => $newValues], $audit->new_values);
    }
}
