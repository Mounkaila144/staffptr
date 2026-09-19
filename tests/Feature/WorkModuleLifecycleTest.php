<?php

namespace Tests\Feature;

use App\Enums\CompanyPriorityState;
use App\Enums\DeliverableStatus;
use App\Enums\ObjectiveState;
use App\Enums\ProjectStatus;
use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\AuditLog;
use App\Models\Work\CompanyPriority;
use App\Models\Work\Deliverable;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Models\Work\ProjectMember;
use App\Services\Work\CompanyPriorityService;
use App\Services\Work\DeliverableService;
use App\Services\Work\ObjectiveService;
use App\Services\Work\ProjectService;
use App\Services\Work\TaskService;
use App\Services\Work\TodayTaskService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class WorkModuleLifecycleTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    #[Test]
    public function ac_1_2_3_and_6_company_priorities_enforce_five_and_cancel_without_deletion(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();
        $service = app(CompanyPriorityService::class);
        foreach (range(1, 5) as $index) {
            $service->create($this->priorityData($actor, "Priorité {$index}"), $actor);
        }
        try {
            $service->create($this->priorityData($actor, 'Sixième'), $actor);
            $this->fail('La sixième priorité doit être refusée.');
        } catch (ValidationException $e) {
            $this->assertSame('La limite de 5 priorités validées pour juillet 2026 est atteinte.', $e->errors()['month'][0]);
        }
        $first = CompanyPriority::query()->firstOrFail();
        $service->cancel($first, 'Priorité devenue sans objet.', $actor);
        $service->create($this->priorityData($actor, 'Remplacement'), $actor);
        $this->assertSame(6, CompanyPriority::query()->count());
        $this->assertSame(5, CompanyPriority::query()->where('state', CompanyPriorityState::Validee)->count());
    }

    #[Test]
    public function ac_5_priority_update_requires_reason_and_audits_old_and_new_values(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();
        $service = app(CompanyPriorityService::class);
        $priority = $service->create($this->priorityData($actor, 'Ancien titre'), $actor);
        try {
            $service->update($priority, [...$this->priorityData($actor, 'Nouveau titre')], '', $actor);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $service->update($priority, [...$this->priorityData($actor, 'Nouveau titre')], 'Clarification mensuelle.', $actor);
        $audit = AuditLog::query()->where('action', 'company_priority_updated')->firstOrFail();
        $this->assertSame('Ancien titre', $audit->old_values['title']);
        $this->assertSame('Nouveau titre', $audit->new_values['title']);
    }

    #[Test]
    public function ac_7_to_13_objective_proposal_and_monthly_limit_apply_to_direction_too(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $service = app(ObjectiveService::class);
        foreach (range(1, 3) as $index) {
            $objective = $service->propose($this->objectiveData($direction, "Objectif {$index}"), $direction);
            $service->validate($objective, $direction);
        }
        $this->assertSame(3, Objective::query()->where('user_id', $direction->getKey())->dueInMonth(CarbonImmutable::parse('2026-07-01'))->count());
        $fourth = $service->propose($this->objectiveData($direction, 'Quatrième objectif'), $direction);
        $this->assertSame(ObjectiveState::Brouillon, $fourth->state);
        try {
            $service->validate($fourth, $direction);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertSame("Vous avez déjà 3 objectifs majeurs validés pour juillet. Terminez-en un ou reportez-le avant d'en valider un quatrième.", $e->errors()['state'][0]);
        }
    }

    #[Test]
    public function ac_10_only_six_official_objective_states_count_toward_the_limit(): void
    {
        foreach (ObjectiveState::cases() as $state) {
            $this->assertSame(! in_array($state, [ObjectiveState::Brouillon, ObjectiveState::Annule], true), $state->countsTowardMonthlyLimit(), $state->value);
        }
    }

    #[Test]
    public function ac_14_and_15_transitions_are_enforced_and_attained_requires_evidence(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();
        $service = app(ObjectiveService::class);
        $objective = $service->propose($this->objectiveData($actor, 'Objectif prouvé'), $actor);
        $service->validate($objective, $actor);
        $service->transition($objective, ObjectiveState::EnCours, $actor);
        try {
            $service->transition($objective, ObjectiveState::Atteint, $actor);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Compte rendu signé', $e->errors()['attachment_ulid'][0]);
        }
        Attachment::factory()->for($objective, 'attachable')->create(['uploaded_by' => $actor->getKey()]);
        $result = $service->transition($objective, ObjectiveState::Atteint, $actor);
        $this->assertSame(ObjectiveState::Atteint, $result->state);
        try {
            $service->transition($result, ObjectiveState::EnCours, $actor);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('state', $e->errors());
        }
    }

    #[Test]
    public function ac_16_18_and_23_updates_are_versioned_audited_and_copies_return_to_draft(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();
        $service = app(ObjectiveService::class);
        $objective = $service->propose($this->objectiveData($actor, 'Version initiale'), $actor);
        $service->validate($objective, $actor);
        $updated = $service->update($objective, [...$this->objectiveData($actor, 'Version corrigée')], 'Cible reformulée.', $actor);
        $this->assertSame(2, $updated->version_number);
        $this->assertDatabaseHas('objective_versions', ['objective_id' => $objective->getKey(), 'version_number' => 2, 'reason' => 'Cible reformulée.']);
        $audit = AuditLog::query()->where('action', 'objective_updated')->firstOrFail();
        $this->assertSame('Version initiale', $audit->old_values['title']);
        $copy = $service->copyToNextMonth($updated, $actor);
        $this->assertSame(ObjectiveState::Brouillon, $copy->state);
        $this->assertSame('2026-08-31', $copy->due_date->format('Y-m-d'));
    }

    #[Test]
    public function ac_21_visibility_is_global_for_direction_team_for_tutor_and_personal_for_others(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $member = User::factory()->active()->withRole('employe')->create(['manager_id' => $tutor->getKey()]);
        $other = User::factory()->active()->withRole('employe')->create();
        Objective::factory()->for($member, 'owner')->create(['created_by' => $member->getKey()]);
        Objective::factory()->for($other, 'owner')->create(['created_by' => $other->getKey()]);
        $this->assertSame(2, Objective::query()->visibleTo($direction)->count());
        $this->assertSame(1, Objective::query()->visibleTo($tutor)->count());
        $this->assertSame(1, Objective::query()->visibleTo($member)->count());
    }

    #[Test]
    public function ac_27_to_32_project_transitions_and_membership_history_are_preserved(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();
        $member = User::factory()->active()->create();
        $service = app(ProjectService::class);
        $project = $service->create($this->projectData($actor), $actor);
        $service->transition($project, ProjectStatus::Actif, 'Projet prêt à démarrer.', $actor);
        $service->addMember($project, $member, '2026-07-01', $actor);
        $service->removeMember($project, $member, '2026-07-20', $actor);
        $this->assertTrue(ProjectMember::query()->where('project_id', $project->getKey())->where('user_id', $member->getKey())->whereDate('left_on', '2026-07-20')->exists());
        $this->assertSame(2, $project->statusHistory()->count());
    }

    #[Test]
    public function ac_33_to_38_task_depth_and_today_contract_are_enforced(): void
    {
        $actor = User::factory()->active()->withRole('employe')->create();
        $service = app(TaskService::class);
        $data = $this->taskData($actor);
        $parent = $service->create($data, $actor);
        $child = $service->create([...$data, 'title' => 'Sous-tâche', 'parent_id' => $parent->getKey()], $actor);
        try {
            $service->create([...$data, 'title' => 'Sous-sous-tâche', 'parent_id' => $child->getKey()], $actor);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }
        $this->assertSame([$parent->getKey(), $child->getKey()], app(TodayTaskService::class)->forUser($actor)->pluck('id')->all());
    }

    #[Test]
    public function ac_35_projects_and_tasks_accept_private_attachments_links_and_comments(): void
    {
        $actor = User::factory()->active()->withRole('direction')->create();
        $projectService = app(ProjectService::class);
        $taskService = app(TaskService::class);
        $project = $projectService->create($this->projectData($actor), $actor);
        $task = $taskService->create([...$this->taskData($actor), 'project_id' => $project->getKey()], $actor);
        $projectAttachment = Attachment::factory()->for($actor->person, 'attachable')->create(['uploaded_by' => $actor->getKey()]);
        $taskAttachment = Attachment::factory()->for($actor->person, 'attachable')->create(['uploaded_by' => $actor->getKey()]);

        $projectService->attach($project, $projectAttachment, $actor);
        $projectService->addLink($project, 'Cahier des charges', 'https://example.test/projet', $actor);
        $projectService->comment($project, 'Décision de cadrage.', $actor);
        $taskService->attach($task, $taskAttachment, $actor);
        $taskService->addLink($task, 'Document de travail', 'https://example.test/tache', $actor);
        $taskService->comment($task, 'Point traité.', $actor);

        $this->assertCount(1, $project->attachments()->get());
        $this->assertCount(1, $project->links()->get());
        $this->assertCount(1, $project->comments()->get());
        $this->assertCount(1, $task->attachments()->get());
        $this->assertCount(1, $task->links()->get());
        $this->assertCount(1, $task->comments()->get());
    }

    #[Test]
    public function ac_39_to_42_deliverable_validation_is_restricted_and_variance_is_explicit(): void
    {
        $manager = User::factory()->active()->withRole('employe')->create();
        $other = User::factory()->active()->withRole('employe')->create();
        $project = Project::factory()->for($manager, 'manager')->create();
        $deliverable = Deliverable::factory()->for($project)->create(['planned_date' => '2026-07-10', 'actual_date' => '2026-07-14', 'status' => DeliverableStatus::Soumis]);
        $service = app(DeliverableService::class);
        $this->assertSame('4 jours de retard', $deliverable->scheduleVarianceLabel());
        $this->expectException(AuthorizationException::class);
        $service->transition($deliverable, DeliverableStatus::Valide, 'Livrable vérifié.', $other);
    }

    /** @return array<string,mixed> */
    private function priorityData(User $owner, string $title): array
    {
        return ['month' => '2026-07-01', 'title' => $title, 'description' => 'Priorité mensuelle mesurable.', 'owner_id' => $owner->getKey(), 'indicator' => 'Taux', 'target' => '100 %', 'due_date' => '2026-07-31', 'priority' => WorkPriority::Haute->value];
    }

    /** @return array<string,mixed> */
    private function objectiveData(User $owner, string $title): array
    {
        return ['user_id' => $owner->getKey(), 'title' => $title, 'description' => 'Description de l’objectif.', 'indicator' => 'Taux', 'target_value' => '100 %', 'expected_evidence' => 'Compte rendu signé', 'required_means' => 'Temps dédié', 'due_date' => '2026-07-31', 'priority' => WorkPriority::Normale->value, 'progress' => 0];
    }

    /** @return array<string,mixed> */
    private function projectData(User $manager): array
    {
        return ['name' => 'Projet test', 'client_name' => 'Client libre', 'manager_id' => $manager->getKey(), 'start_date' => '2026-07-01', 'end_date' => '2026-08-01', 'status' => ProjectStatus::Prevu->value, 'planned_budget_xof' => 100000, 'spent_budget_xof' => 0];
    }

    /** @return array<string,mixed> */
    private function taskData(User $assignee): array
    {
        return ['assignee_id' => $assignee->getKey(), 'title' => 'Tâche du jour', 'due_date' => now('Africa/Niamey')->toDateString(), 'priority' => WorkPriority::Normale->value, 'status' => WorkTaskStatus::AFaire->value];
    }
}
