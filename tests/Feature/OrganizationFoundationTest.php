<?php

namespace Tests\Feature;

use App\Models\Identity\Company;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\OrganizationService;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Platform\AuditLogService;
use Database\Seeders\CompanySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class OrganizationFoundationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_1_company_is_database_singleton_without_tenant_or_deletion_columns(): void
    {
        $columns = $this->migrationSchema()->getColumnListing('companies');

        $this->assertContains('singleton_lock', $columns);
        $this->assertNotContains('tenant_id', $columns);
        $this->assertNotContains('deleted_at', $columns);
        $this->assertNotContains('deleted_at', $this->migrationSchema()->getColumnListing('departments'));
        $this->assertNotContains('deleted_at', $this->migrationSchema()->getColumnListing('job_functions'));

        DB::table('companies')->insert([
            'name' => 'Première entreprise',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('companies')->insert([
                'name' => 'Seconde entreprise',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail("L'index unique de singleton devait refuser une seconde entreprise.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('unique', mb_strtolower($exception->getMessage()));
        }

        $this->assertSame(1, DB::table('companies')->count());
    }

    public function test_ac_3_department_with_active_members_requires_reassignment_before_deactivation(): void
    {
        $actor = $this->directionUser();
        $department = Department::factory()->create(['name' => 'Opérations']);
        User::factory()->count(3)->active()->create(['department_id' => $department->getKey()]);
        $service = app(OrganizationService::class);

        try {
            $service->deactivateDepartment($department, $actor);
            $this->fail('La désactivation devait être refusée tant que trois membres restent rattachés.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Ce service compte encore 3 membres. Réaffectez-les avant de le désactiver.',
                $exception->errors()['department'][0],
            );
        }

        $this->assertTrue($department->fresh()->is_active);
        User::query()->where('department_id', $department->getKey())->update(['department_id' => null]);

        $service->deactivateDepartment($department->fresh(), $actor);

        $this->assertFalse($department->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Department::class,
            'auditable_id' => $department->getKey(),
            'action' => 'department_deactivated',
            'actor_id' => $actor->getKey(),
        ]);
    }

    public function test_ac_4_company_seeder_is_idempotent_and_keeps_one_ptr_niger_record(): void
    {
        $this->seed(CompanySeeder::class);
        $first = Company::query()->sole();
        $firstUpdatedAt = $first->updated_at;

        $this->seed(CompanySeeder::class);

        $company = Company::query()->sole();
        $this->assertSame($first->getKey(), $company->getKey());
        $this->assertSame('PTR Niger', $company->name);
        $this->assertTrue($firstUpdatedAt->equalTo($company->updated_at));
    }

    public function test_ac_5_company_department_and_function_writes_are_explicitly_audited_in_french(): void
    {
        $actor = $this->directionUser();
        $company = Company::factory()->create(['name' => 'PTR']);
        $service = app(OrganizationService::class);

        $service->updateCompany($company, ['name' => 'PTR Niger'], $actor);
        $department = $service->createDepartment('Conseil', $actor);
        $service->deactivateDepartment($department, $actor);
        $jobFunction = $service->createJobFunction('Consultant', $actor);
        $service->deactivateJobFunction($jobFunction, $actor);

        foreach (array_keys($this->expectedActions()) as $action) {
            $entry = AuditLog::query()->where('action', $action)->sole();
            $this->assertSame($actor->getKey(), $entry->actor_id, $action);
            $this->assertSame($actor->person->full_name, $entry->actor_label, $action);
        }

        $auditData = app(AuditLogService::class)->indexData(['action' => 'department_deactivated']);
        $displayed = $auditData['entries']['data'][0];
        $changes = collect($displayed['changes'])->keyBy('field');

        $this->assertStringStartsWith('Service #', $displayed['object']);
        $this->assertSame('Désactivation du service', $displayed['action']);
        $this->assertSame('Actif', $changes['is_active']['label']);
        $this->assertSame('Oui', $changes['is_active']['old']);
        $this->assertSame('Non', $changes['is_active']['new']);
    }

    /** @return array<string, class-string> */
    private function expectedActions(): array
    {
        return [
            'company_updated' => Company::class,
            'department_created' => Department::class,
            'department_deactivated' => Department::class,
            'job_function_created' => JobFunction::class,
            'job_function_deactivated' => JobFunction::class,
        ];
    }

    private function directionUser(): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, 'direction', null, 'Test story 3.1');

        return $user->fresh('person');
    }
}
