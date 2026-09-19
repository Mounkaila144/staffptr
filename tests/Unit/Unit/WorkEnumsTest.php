<?php

namespace Tests\Unit\Unit;

use App\Enums\DeliverableStatus;
use App\Enums\ObjectiveState;
use App\Enums\ProjectStatus;
use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Models\Work\Deliverable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WorkEnumsTest extends TestCase
{
    #[Test]
    public function ac_14_objective_transition_matrix_matches_the_confirmed_contract(): void
    {
        $this->assertTrue(ObjectiveState::Brouillon->canTransitionTo(ObjectiveState::Valide));
        $this->assertTrue(ObjectiveState::EnCours->canTransitionTo(ObjectiveState::Atteint));
        $this->assertTrue(ObjectiveState::Bloque->canTransitionTo(ObjectiveState::EnCours));
        $this->assertFalse(ObjectiveState::Atteint->canTransitionTo(ObjectiveState::EnCours));
        $this->assertCount(8, ObjectiveState::cases());
    }

    #[Test]
    public function ac_17_every_objective_state_has_text_and_a_non_color_label(): void
    {
        foreach (ObjectiveState::cases() as $state) {
            $this->assertNotSame('', $state->label());
            $this->assertContains($state->tone(), ['vert', 'orange', 'rouge', 'gris']);
        }
    }

    #[Test]
    public function ac_28_project_transition_matrix_matches_the_confirmed_contract(): void
    {
        $this->assertTrue(ProjectStatus::Prevu->canTransitionTo(ProjectStatus::Actif));
        $this->assertTrue(ProjectStatus::Bloque->canTransitionTo(ProjectStatus::Actif));
        $this->assertFalse(ProjectStatus::Cloture->canTransitionTo(ProjectStatus::Actif));
        $this->assertCount(7, ProjectStatus::cases());
    }

    #[Test]
    public function ac_33_task_and_priority_vocabularies_are_complete(): void
    {
        $this->assertSame(['basse', 'normale', 'haute', 'urgente'], array_column(WorkPriority::cases(), 'value'));
        $this->assertSame(['a_faire', 'en_cours', 'bloquee', 'terminee', 'annulee'], array_column(WorkTaskStatus::cases(), 'value'));
    }

    #[Test]
    public function ac_39_to_41_deliverable_states_and_date_variance_are_pure_and_explicit(): void
    {
        $this->assertCount(5, DeliverableStatus::cases());
        $this->assertSame("4 jours d'avance", Deliverable::varianceLabel(-4));
    }
}
