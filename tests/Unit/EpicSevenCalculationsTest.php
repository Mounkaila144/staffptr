<?php

namespace Tests\Unit;

use App\Enums\AlertLevel;
use App\Enums\InternshipChecklistType;
use App\Enums\InternshipEvaluationType;
use App\Enums\InternshipIntakeState;
use App\Enums\InternshipState;
use App\Enums\ReviewObjectiveStatus;
use App\Enums\WeeklyReviewState;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\InternshipIntakeForm;
use App\Services\Identity\InternActivationReadiness;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Task 12 — calculs purs de l'Epic 7, testés sans base de données.
 */
class EpicSevenCalculationsTest extends TestCase
{
    /**
     * AC 9 : la durée d'un plan se compte bornes incluses. Sept jours du 10 au 16, pas du 10 au 17.
     */
    public function test_ac_9_plan_duration_counts_both_bounds(): void
    {
        $plan = new ImprovementPlan;
        $plan->setRawAttributes([
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-16',
        ]);

        $this->assertSame(7, $plan->durationInDays());
        $this->assertSame(7, ImprovementPlan::MINIMUM_DURATION_DAYS);
        $this->assertSame(14, ImprovementPlan::MAXIMUM_DURATION_DAYS);
    }

    /**
     * AC 1 : le vendredi est le cinquième jour de la semaine civile commençant le lundi.
     */
    public function test_ac_1_friday_is_four_days_after_the_week_start(): void
    {
        $monday = CarbonImmutable::parse('2026-08-10', 'Africa/Niamey');

        $this->assertSame('lundi', $monday->locale('fr')->dayName);
        $this->assertSame('vendredi', $monday->addDays(4)->locale('fr')->dayName);
        $this->assertSame('2026-08-14', $monday->addDays(4)->toDateString());
    }

    /**
     * AC 3 : la cause de l'écart n'est exigée que lorsque le résultat s'écarte de l'objectif.
     */
    public function test_ac_3_gap_cause_is_required_only_when_the_objective_is_not_met(): void
    {
        $this->assertFalse(ReviewObjectiveStatus::Atteint->requiresGapCause());
        $this->assertTrue(ReviewObjectiveStatus::PartiellementAtteint->requiresGapCause());
        $this->assertTrue(ReviewObjectiveStatus::NonAtteint->requiresGapCause());
    }

    /**
     * AC 7 : seule une revue validée est figée.
     */
    public function test_ac_7_only_a_validated_review_is_frozen(): void
    {
        $this->assertTrue(WeeklyReviewState::Brouillon->isEditable());
        $this->assertTrue(WeeklyReviewState::EnAttenteValidation->isEditable());
        $this->assertFalse(WeeklyReviewState::Validee->isEditable());
    }

    /**
     * AC 23 : seuls les stages actifs occupent une place chez le tuteur.
     */
    public function test_ac_23_only_active_internships_occupy_a_tutor_slot(): void
    {
        $this->assertTrue(InternshipState::Actif->occupiesTutorSlot());
        $this->assertFalse(InternshipState::Termine->occupiesTutorSlot());
        $this->assertFalse(InternshipState::Archive->occupiesTutorSlot());
        $this->assertSame(['actif'], InternshipState::occupyingValues());
    }

    /**
     * AC 14 : trois résultats attendus au minimum.
     */
    public function test_ac_14_three_outcomes_are_required(): void
    {
        $this->assertSame(3, InternshipIntakeForm::REQUIRED_OUTCOMES);
    }

    /**
     * AC 16 : trois objectifs attendus avant activation.
     */
    public function test_ac_16_three_objectives_are_required_before_activation(): void
    {
        $this->assertSame(3, InternActivationReadiness::REQUIRED_OBJECTIVES);
    }

    /**
     * AC 17 et 30 : les deux checklists portent leurs libellés de référence, dans l'ordre.
     */
    public function test_ac_17_and_30_both_checklists_carry_their_reference_items(): void
    {
        $integration = InternshipChecklistType::Integration->items();
        $exit = InternshipChecklistType::Sortie->items();

        $this->assertCount(6, $integration);
        $this->assertSame('Contrat ou convention signé', $integration[0]);
        $this->assertSame('Tuteur présenté', $integration[5]);

        $this->assertCount(5, $exit);
        $this->assertSame('Livrables remis', $exit[0]);
        $this->assertSame('Évaluation finale enregistrée', $exit[4]);

        // Aucun libellé partagé entre les deux listes.
        $this->assertSame([], array_intersect($integration, $exit));
    }

    /**
     * AC 18 : seul le niveau rouge bloque l'activation d'un compte.
     */
    public function test_ac_18_only_the_red_level_blocks_an_activation(): void
    {
        $this->assertFalse(AlertLevel::Vert->blocksAccountActivation());
        $this->assertFalse(AlertLevel::Orange->blocksAccountActivation());
        $this->assertTrue(AlertLevel::Rouge->blocksAccountActivation());
    }

    /**
     * AC 29 : seule l'évaluation finale porte l'indication d'éligibilité à l'attestation.
     */
    public function test_ac_29_only_the_final_evaluation_carries_certificate_eligibility(): void
    {
        $this->assertFalse(InternshipEvaluationType::Hebdomadaire->carriesCertificateEligibility());
        $this->assertTrue(InternshipEvaluationType::Finale->carriesCertificateEligibility());
    }

    /**
     * AC 15 : le circuit de la fiche d'entrée ne comporte aucune étape intermédiaire.
     */
    public function test_ac_15_the_intake_circuit_has_no_intermediate_step(): void
    {
        $states = array_map(
            static fn (InternshipIntakeState $state): string => $state->value,
            InternshipIntakeState::cases(),
        );

        $this->assertSame(['brouillon', 'soumise', 'approuvee', 'refusee'], $states);
    }
}
