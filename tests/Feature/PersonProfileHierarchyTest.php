<?php

namespace Tests\Feature;

use App\Enums\RelationType;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\HierarchyService;
use App\Services\Identity\PersonProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class PersonProfileHierarchyTest extends IdentityTestCase
{
    public function test_ac_1_profile_persists_all_fields_with_typed_enum_and_business_dates(): void
    {
        $actor = User::factory()->active()->leader()->create();
        $manager = User::factory()->active()->leader()->create();
        $target = User::factory()->active()->create();
        $department = Department::factory()->create();
        $jobFunction = JobFunction::factory()->create();

        app(PersonProfileService::class)->update(
            $target->person,
            $target,
            [
                'full_name' => 'Aïcha Amadou',
                'photo_path' => 'people/aicha.webp',
                'phone' => '+22790001122',
                'department_id' => $department->getKey(),
                'job_function_id' => $jobFunction->getKey(),
                'manager_id' => $manager->getKey(),
                'relation_type' => RelationType::Contractuel->value,
                'contract_start_date' => '2026-01-10',
                'contract_end_date' => '2026-12-31',
            ],
            $actor,
        );

        $target->refresh();
        $this->assertSame('Aïcha Amadou', $target->person->fresh()->full_name);
        $this->assertSame('people/aicha.webp', $target->person->fresh()->photo_path);
        $this->assertSame('+22790001122', $target->phone);
        $this->assertSame($department->getKey(), $target->department_id);
        $this->assertSame($jobFunction->getKey(), $target->job_function_id);
        $this->assertSame($manager->getKey(), $target->manager_id);
        $this->assertSame(RelationType::Contractuel, $target->relation_type);
        $this->assertInstanceOf(CarbonImmutable::class, $target->contract_start_date);
        $this->assertSame('2026-01-10', $target->contract_start_date->toDateString());
        $this->assertSame('2026-12-31', $target->contract_end_date->toDateString());
    }

    public function test_ac_2_hierarchy_exposes_only_active_direct_manager_and_subordinates(): void
    {
        $manager = User::factory()->active()->leader()->create();
        $target = User::factory()->active()->withManager($manager)->create();
        $direct = User::factory()->active()->withManager($target)->create();
        User::factory()->suspended()->withManager($target)->create();
        User::factory()->active()->withManager($direct)->create();

        $hierarchy = app(HierarchyService::class)->directRelations($target);

        $this->assertTrue($hierarchy['manager']?->is($manager));
        $this->assertSame([$direct->getKey()], $hierarchy['subordinates']->modelKeys());
    }

    public function test_ac_3_three_account_cycle_and_self_assignment_are_refused_but_unrelated_manager_is_allowed(): void
    {
        $actor = User::factory()->active()->leader()->create();
        $accountA = User::factory()->active()->withoutManager()->create();
        $accountB = User::factory()->active()->withManager($accountA)->create();
        $accountC = User::factory()->active()->withManager($accountB)->create();
        $unrelated = User::factory()->active()->leader()->create();
        $service = app(PersonProfileService::class);

        $this->assertManagerValidationError(fn () => $service->update(
            $accountA->person,
            $accountA,
            $this->profileAttributes($accountA, $accountC->getKey()),
            $actor,
        ));
        $this->assertNull($accountA->fresh()->manager_id);

        $this->assertManagerValidationError(fn () => $service->update(
            $accountA->person,
            $accountA,
            $this->profileAttributes($accountA, $accountA->getKey()),
            $actor,
        ));

        $service->update(
            $accountA->person,
            $accountA,
            $this->profileAttributes($accountA, $unrelated->getKey()),
            $actor,
        );

        $this->assertSame($unrelated->getKey(), $accountA->fresh()->manager_id);
    }

    public function test_ac_1_and_5_profile_and_manager_changes_are_audited_with_the_actor(): void
    {
        $actor = User::factory()->active()->leader()->create();
        $manager = User::factory()->active()->leader()->create();
        $target = User::factory()->active()->create();

        app(PersonProfileService::class)->update(
            $target->person,
            $target,
            [
                ...$this->profileAttributes($target, $manager->getKey()),
                'full_name' => 'Nom corrigé',
                'relation_type' => RelationType::Contractuel->value,
            ],
            $actor,
        );

        foreach (['person_profile_updated', 'user_profile_updated', 'manager_changed'] as $action) {
            $audit = AuditLog::query()->where('action', $action)->sole();
            $this->assertSame($actor->getKey(), $audit->actor_id, $action);
            $this->assertSame($actor->person->full_name, $audit->actor_label, $action);
        }

        $this->assertTrue($this->migrationSchema()->hasTable('user_history'));
        $this->assertDatabaseHas('user_history', [
            'user_id' => $target->getKey(),
            'field' => 'manager_id',
            'new_value' => $manager->person->full_name,
            'changed_by' => $actor->getKey(),
        ]);
    }

    /** @return array<string, mixed> */
    private function profileAttributes(User $user, ?int $managerId): array
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

    private function assertManagerValidationError(callable $operation): void
    {
        try {
            $operation();
            $this->fail('La modification devait être refusée pour éviter un cycle hiérarchique.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Ce responsable créerait un cycle hiérarchique. Choisissez une personne hors de cette chaîne.',
                $exception->errors()['manager_id'][0],
            );
        }
    }
}
