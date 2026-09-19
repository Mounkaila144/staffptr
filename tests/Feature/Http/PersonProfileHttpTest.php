<?php

namespace Tests\Feature\Http;

use App\Enums\RelationType;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class PersonProfileHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_2_show_returns_server_computed_direct_manager_and_subordinates(): void
    {
        $manager = $this->userWithRole('tuteur');
        $target = $this->userWithRole('employe', ['manager_id' => $manager->getKey()]);
        $direct = User::factory()->active()->withManager($target)->create();
        User::factory()->active()->withManager($direct)->create();

        $this->actingAs($manager)
            ->get(route('people.show', $target->person))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Identity/People/Show')
                ->where('profile.person_id', $target->person_id)
                ->where('manager.id', $manager->getKey())
                ->has('subordinates', 1)
                ->where('subordinates.0.id', $direct->getKey()));
    }

    public function test_ac_5_visibility_is_limited_to_self_direct_team_and_direction(): void
    {
        $manager = $this->userWithRole('tuteur');
        $target = $this->userWithRole('employe', ['manager_id' => $manager->getKey()]);
        $peer = $this->userWithRole('employe');
        $direction = $this->userWithRole('direction');
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($target)->get(route('people.show', $target->person))->assertOk();
        $this->resetAuthentication();
        $this->actingAs($manager)->get(route('people.show', $target->person))->assertOk();
        $this->resetAuthentication();
        $this->actingAs($direction)->get(route('people.show', $target->person))->assertOk();
        $this->resetAuthentication();
        $this->actingAs($peer)->get(route('people.show', $target->person))->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($superAdmin)->get(route('people.show', $target->person))->assertForbidden();
        $this->assertTrue(Gate::forUser($superAdmin)->denies('view', $target->person));
    }

    public function test_ac_1_direction_updates_every_profile_field_while_other_roles_cannot_edit(): void
    {
        $direction = $this->userWithRole('direction');
        $target = $this->userWithRole('employe');
        $manager = User::factory()->active()->leader()->create();
        $department = Department::factory()->create();
        $jobFunction = JobFunction::factory()->create();
        $payload = [
            'full_name' => 'Moussa Issoufou',
            'phone' => '+22790112233',
            'department_id' => $department->getKey(),
            'job_function_id' => $jobFunction->getKey(),
            'manager_id' => $manager->getKey(),
            'relation_type' => RelationType::Stagiaire->value,
            'contract_start_date' => '2026-02-01',
            'contract_end_date' => '2026-08-31',
        ];

        $this->actingAs($direction)
            ->patch(route('people.update', $target->person), $payload)
            ->assertRedirect(route('people.show', $target->person));

        $target->refresh();
        $this->assertSame('Moussa Issoufou', $target->person->fresh()->full_name);
        $this->assertSame('+22790112233', $target->phone);
        $this->assertSame($department->getKey(), $target->department_id);
        $this->assertSame($jobFunction->getKey(), $target->job_function_id);
        $this->assertSame($manager->getKey(), $target->manager_id);
        $this->assertSame(RelationType::Stagiaire, $target->relation_type);
        $this->assertSame('2026-02-01', $target->contract_start_date->toDateString());
        $this->assertSame('2026-08-31', $target->contract_end_date->toDateString());

        $this->resetAuthentication();
        $this->actingAs($target)
            ->patch(route('people.update', $target->person), [...$payload, 'full_name' => 'Interdit'])
            ->assertForbidden();
        $this->assertSame('Moussa Issoufou', $target->person->fresh()->full_name);
    }

    public function test_ac_3_form_request_refuses_a_three_account_cycle_and_self_assignment(): void
    {
        $direction = $this->userWithRole('direction');
        $accountA = User::factory()->active()->withoutManager()->create();
        $accountB = User::factory()->active()->withManager($accountA)->create();
        $accountC = User::factory()->active()->withManager($accountB)->create();

        $this->actingAs($direction)
            ->patch(route('people.update', $accountA->person), $this->payload($accountA, $accountC->getKey()))
            ->assertSessionHasErrors('manager_id');
        $this->assertNull($accountA->fresh()->manager_id);

        $this->actingAs($direction)
            ->patch(route('people.update', $accountA->person), $this->payload($accountA, $accountA->getKey()))
            ->assertSessionHasErrors('manager_id');
        $this->assertNull($accountA->fresh()->manager_id);
    }

    public function test_ac_1_photo_type_and_size_are_validated_on_the_server(): void
    {
        $direction = $this->userWithRole('direction');
        $target = User::factory()->active()->create();

        $this->actingAs($direction)
            ->patch(route('people.update', $target->person), [
                ...$this->payload($target, null),
                'photo' => UploadedFile::fake()->create('photo.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($target->person->fresh()->photo_path);
    }

    public function test_ac_6_profile_page_keeps_the_320px_stacked_layout_contract(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Identity/People/Show.vue'));

        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringContainsString('sm:grid-cols-2', $source);
        $this->assertStringContainsString('Aucun subordonné direct.', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->active()->create($attributes);
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 3.2');

        return $user->fresh('person');
    }

    /** @return array<string, mixed> */
    private function payload(User $user, ?int $managerId): array
    {
        return [
            'full_name' => $user->person->full_name,
            'phone' => $user->phone,
            'department_id' => $user->department_id,
            'job_function_id' => $user->job_function_id,
            'manager_id' => $managerId,
            'relation_type' => $user->relation_type->value,
            'contract_start_date' => $user->contract_start_date?->toDateString(),
            'contract_end_date' => $user->contract_end_date?->toDateString(),
        ];
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
