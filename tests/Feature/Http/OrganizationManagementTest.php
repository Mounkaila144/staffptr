<?php

namespace Tests\Feature\Http;

use App\Models\Identity\Company;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Database\Seeders\CompanySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Support\IdentityTestCase;

class OrganizationManagementTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        $this->seed(CompanySeeder::class);
    }

    public function test_ac_1_only_the_existing_company_can_be_updated_and_no_creation_route_exists(): void
    {
        $direction = $this->userWithRole('direction');

        $this->assertFalse(Route::has('organisation.company.store'));
        $this->actingAs($direction)->post('/organisation/entreprise', ['name' => 'Deuxième'])->assertMethodNotAllowed();

        $this->actingAs($direction)
            ->patch('/organisation/entreprise', ['name' => 'PTR Niger mise à jour'])
            ->assertRedirect(route('organisation.index'));

        $this->assertSame(1, Company::query()->count());
        $this->assertSame('PTR Niger mise à jour', Company::query()->sole()->name);
    }

    public function test_ac_2_direction_manages_services_and_functions_without_deleting_rows(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)->get('/organisation')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Identity/Organization/Index')
                ->has('company')
                ->has('departments')
                ->has('jobFunctions'));

        $this->actingAs($direction)->post('/organisation/services', ['name' => 'Technique'])
            ->assertRedirect(route('organisation.index'));
        $department = Department::query()->where('name', 'Technique')->sole();
        $this->actingAs($direction)->patch("/organisation/services/{$department->getKey()}", ['name' => 'Ingénierie'])
            ->assertRedirect(route('organisation.index'));
        $this->actingAs($direction)->patch("/organisation/services/{$department->getKey()}/desactiver")
            ->assertRedirect(route('organisation.index'));
        $this->assertFalse($department->fresh()->is_active);
        $this->actingAs($direction)->patch("/organisation/services/{$department->getKey()}/reactiver")
            ->assertRedirect(route('organisation.index'));
        $this->assertTrue($department->fresh()->is_active);
        $this->assertSame(1, Department::query()->count());

        $this->actingAs($direction)->post('/organisation/fonctions', ['name' => 'Développeur'])
            ->assertRedirect(route('organisation.index'));
        $jobFunction = JobFunction::query()->where('name', 'Développeur')->sole();
        $this->actingAs($direction)->patch("/organisation/fonctions/{$jobFunction->getKey()}", ['name' => 'Développeur senior'])
            ->assertRedirect(route('organisation.index'));
        $this->actingAs($direction)->patch("/organisation/fonctions/{$jobFunction->getKey()}/desactiver")
            ->assertRedirect(route('organisation.index'));
        $this->assertFalse($jobFunction->fresh()->is_active);
        $this->actingAs($direction)->patch("/organisation/fonctions/{$jobFunction->getKey()}/reactiver")
            ->assertRedirect(route('organisation.index'));
        $this->assertTrue($jobFunction->fresh()->is_active);
        $this->assertSame(1, JobFunction::query()->count());
    }

    public function test_ac_2_five_other_roles_receive_403_on_every_organization_write_route(): void
    {
        $department = Department::factory()->create();
        $jobFunction = JobFunction::factory()->create();
        $writes = [
            ['PATCH', '/organisation/entreprise', ['name' => 'Interdit']],
            ['POST', '/organisation/services', ['name' => 'Interdit']],
            ['PATCH', "/organisation/services/{$department->getKey()}", ['name' => 'Interdit']],
            ['PATCH', "/organisation/services/{$department->getKey()}/desactiver", []],
            ['PATCH', "/organisation/services/{$department->getKey()}/reactiver", []],
            ['POST', '/organisation/fonctions', ['name' => 'Interdit']],
            ['PATCH', "/organisation/fonctions/{$jobFunction->getKey()}", ['name' => 'Interdit']],
            ['PATCH', "/organisation/fonctions/{$jobFunction->getKey()}/desactiver", []],
            ['PATCH', "/organisation/fonctions/{$jobFunction->getKey()}/reactiver", []],
        ];

        foreach (['super_admin', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            Auth::forgetGuards();
            $user = $this->userWithRole($role);

            foreach ($writes as [$method, $path, $payload]) {
                $response = $this->actingAs($user)->call($method, $path, $payload);

                $this->assertSame(
                    403,
                    $response->getStatusCode(),
                    "{$role} doit recevoir 403 sur {$method} {$path}, redirection vers ".($response->headers->get('Location') ?? 'aucune'),
                );
            }
        }
    }

    public function test_ac_1_logo_type_and_size_are_validated_on_the_server(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->patch('/organisation/entreprise', [
                'name' => 'PTR Niger',
                'logo' => UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertNull(Company::query()->sole()->logo_path);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 3.1');

        return $user;
    }
}
