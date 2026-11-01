<?php

namespace Tests\Feature;

use App\Enums\DeliverableStatus;
use App\Enums\ObjectiveState;
use App\Enums\ProjectStatus;
use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;
use App\Models\Work\Deliverable;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

/**
 * Ce que les écrans du module Travail lisent pour proposer les bonnes actions :
 * passages d'état possibles, données de modification, historique et écart des livrables.
 */
class WorkScreensContractTest extends IdentityTestCase
{
    public function test_a_deliverable_without_actual_date_has_no_schedule_variance(): void
    {
        $deliverable = Deliverable::factory()->create(['planned_date' => '2026-11-15', 'actual_date' => null]);

        $this->assertNull($deliverable->scheduleVarianceDays());
        $this->assertSame('Date réelle non renseignée', $deliverable->scheduleVarianceLabel());

        $deliverable->actual_date = CarbonImmutable::parse('2026-11-18');
        $this->assertSame(3, $deliverable->scheduleVarianceDays());
        $this->assertSame('3 jours de retard', $deliverable->scheduleVarianceLabel());

        $deliverable->actual_date = CarbonImmutable::parse('2026-11-14');
        $this->assertSame("1 jour d'avance", $deliverable->scheduleVarianceLabel());
    }

    public function test_objective_page_exposes_allowed_transitions_history_and_edit_data(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();
        $objective = Objective::factory()->create(['user_id' => $employee->getKey(), 'created_by' => $employee->getKey(), 'state' => ObjectiveState::Valide, 'due_date' => '2026-11-20']);

        $this->actingAs($employee)
            ->post("/objectifs/{$objective->getKey()}/commentaires", ['body' => 'Point d’étape.', 'correction_requested' => false])
            ->assertSessionHasNoErrors();
        $this->actingAs($employee)
            ->patch("/objectifs/{$objective->getKey()}", [
                'user_id' => $employee->getKey(), 'title' => 'Titre revu', 'description' => $objective->description,
                'indicator' => $objective->indicator, 'target_value' => $objective->target_value,
                'expected_evidence' => $objective->expected_evidence, 'due_date' => '2026-11-20',
                'priority' => 'normale', 'progress' => 40, 'reason' => 'Précision du titre.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($employee)->get("/objectifs/{$objective->getKey()}")
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Work/Objectives/Show')
                ->where('objective.user_id', (int) $employee->getKey())
                ->where('objective.due_date', '2026-11-20')
                ->where('objective.due_date_label', '20/11/2026')
                ->where('objective.progress', 40)
                ->where('objective.transitions', [
                    ['value' => 'en_cours', 'label' => 'En cours'],
                    ['value' => 'bloque', 'label' => 'Bloqué'],
                    ['value' => 'annule', 'label' => 'Annulé'],
                ])
                ->has('objective.comments', 1)
                ->where('objective.comments.0.body', 'Point d’étape.')
                ->has('objective.versions', 1)
                ->where('objective.versions.0.reason', 'Précision du titre.')
                ->where('canUpdate', true)
                ->where('canValidate', false)
                ->where('canCopy', true));
    }

    public function test_project_page_offers_only_reachable_statuses(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $project = Project::factory()->create(['status' => ProjectStatus::Prevu]);

        $this->actingAs($direction)->get("/projets/{$project->getKey()}")
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('project.transitions', [
                    ['value' => 'actif', 'label' => 'Actif'],
                    ['value' => 'bloque', 'label' => 'Bloqué'],
                    ['value' => 'annule', 'label' => 'Annulé'],
                ]));
    }

    public function test_deliverable_validation_is_only_offered_to_those_who_may_validate(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        Deliverable::factory()->create(['owner_id' => $owner->getKey(), 'status' => DeliverableStatus::Soumis, 'actual_date' => '2026-11-10']);

        $this->actingAs($owner)->get('/livrables')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('canCreate', true)
                ->where('deliverables.data.0.transitions', [
                    ['value' => 'correction_demandee', 'label' => 'Correction demandée'],
                    ['value' => 'annule', 'label' => 'Annulé'],
                ]));
    }

    public function test_direction_is_offered_deliverable_validation(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        Deliverable::factory()->create(['status' => DeliverableStatus::Soumis, 'actual_date' => '2026-11-10']);

        $this->actingAs($direction)->get('/livrables')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('deliverables.data.0.transitions.1', ['value' => 'valide', 'label' => 'Validé']));
    }

    public function test_company_priority_list_carries_what_the_edit_form_needs(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $priority = CompanyPriority::factory()->create(['owner_id' => $direction->getKey()]);

        $this->actingAs($direction)->get('/priorites-entreprise')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('priorities.0.owner_id', (int) $direction->getKey())
                ->where('priorities.0.due_date_value', $priority->due_date->format('Y-m-d'))
                ->where('priorities.0.cancellation_reason', null));
    }
}
