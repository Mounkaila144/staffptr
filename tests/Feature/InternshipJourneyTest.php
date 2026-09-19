<?php

namespace Tests\Feature;

use App\Enums\InternshipChecklistType;
use App\Enums\InternshipEvaluationType;
use App\Enums\InternshipState;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipEvaluation;
use App\Models\Identity\User;
use App\Services\Accountability\InternshipEvaluationService;
use App\Services\Accountability\TutorCapacityService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Task 6 — plan de stage, évaluations, attestation et sortie (AC 27 à 33).
 */
class InternshipJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_ac_27_the_internship_plan_carries_skills_objectives_tasks_and_evidence(): void
    {
        [$tutor, , $internship] = $this->internship();

        $plan = $this->service()->savePlan($internship, $tutor, [
            'skills_to_learn' => 'Rédaction de procédure et relecture.',
            'objectives' => 'Documenter le module de suivi.',
            'weekly_tasks' => 'Une procédure par semaine.',
            'expected_evidence' => 'Document versionné et relu.',
        ]);

        $this->assertSame('Rédaction de procédure et relecture.', $plan->skills_to_learn);
        $this->assertSame('Documenter le module de suivi.', $plan->objectives);
        $this->assertSame('Une procédure par semaine.', $plan->weekly_tasks);
        $this->assertSame('Document versionné et relu.', $plan->expected_evidence);
        $this->assertDatabaseHas('audit_logs', ['action' => 'internship_plan_created']);

        // Le plan est unique par stage : un second enregistrement le met à jour.
        $this->service()->savePlan($internship, $tutor, [
            'skills_to_learn' => 'Corrigé.',
            'objectives' => 'Corrigé.',
            'weekly_tasks' => 'Corrigé.',
            'expected_evidence' => 'Corrigé.',
        ]);
        $this->assertDatabaseCount('internship_plans', 1);
    }

    /**
     * AC 28 : le tuteur enregistre l'évaluation hebdomadaire, le stagiaire la consulte.
     */
    public function test_ac_28_the_tutor_records_a_weekly_evaluation_the_intern_can_read(): void
    {
        [$tutor, $intern, $internship] = $this->internship();

        $evaluation = $this->service()->recordWeekly(
            $internship,
            $tutor,
            CarbonImmutable::parse('2026-08-12', 'Africa/Niamey'),
            $this->evaluationData(),
        );

        $this->assertSame(InternshipEvaluationType::Hebdomadaire, $evaluation->type);
        // La semaine est ramenée à son lundi, en date civile de Niamey.
        $this->assertSame('2026-08-10', $evaluation->week_start_date?->toDateString());
        $this->assertSame($tutor->getKey(), $evaluation->evaluator_id);

        // Le stagiaire voit son évaluation dans sa portée de visibilité.
        $this->assertSame(1, InternshipEvaluation::query()->visibleTo($intern)->count());
        $this->assertSame(1, InternshipEvaluation::query()->visibleTo($tutor)->count());
        $this->assertSame(0, InternshipEvaluation::query()->visibleTo(
            User::factory()->active()->withRole('employe')->create(),
        )->count());
    }

    public function test_ac_28_the_same_week_is_updated_not_duplicated(): void
    {
        [$tutor, , $internship] = $this->internship();
        $week = CarbonImmutable::parse('2026-08-10', 'Africa/Niamey');

        $this->service()->recordWeekly($internship, $tutor, $week, $this->evaluationData());
        $this->service()->recordWeekly($internship, $tutor, $week->addDays(3), $this->evaluationData('Corrigé.'));

        $this->assertSame(1, $internship->evaluations()->count());
        $this->assertSame('Corrigé.', $internship->evaluations()->firstOrFail()->observed_progress);
    }

    /**
     * AC 29 : l'évaluation finale indique si les conditions d'attestation sont remplies, sans
     * produire aucun document.
     */
    public function test_ac_29_the_final_evaluation_states_certificate_eligibility_without_a_document(): void
    {
        [$tutor, , $internship] = $this->internship();

        // Sans plan ni évaluation hebdomadaire, les conditions ne sont pas remplies.
        $incomplete = $this->service()->recordFinal($internship, $tutor, $this->evaluationData());
        $this->assertFalse($incomplete->certificate_conditions_met);

        $this->completeConditions($internship, $tutor);
        $complete = $this->service()->recordFinal($internship->refresh(), $tutor, $this->evaluationData());

        $this->assertTrue($complete->certificate_conditions_met);
        $this->assertSame(InternshipEvaluationType::Finale, $complete->type);
        $this->assertNull($complete->week_start_date);
        // Une seule évaluation finale par stage, garantie par le service.
        $this->assertSame(1, $internship->evaluations()->where('type', InternshipEvaluationType::Finale)->count());

        // Aucun document n'est produit : le dossier ne porte qu'une indication.
        $dossier = $this->service()->dossier($internship->refresh());
        $this->assertTrue($dossier['certificate_conditions_met']);
        $this->assertArrayNotHasKey('certificate_url', $dossier);
        $this->assertArrayNotHasKey('certificate_document', $dossier);
    }

    /**
     * AC 30 : la checklist de sortie est générée à la clôture, avec ses cinq éléments.
     */
    public function test_ac_30_the_exit_checklist_is_generated_when_the_internship_ends(): void
    {
        [$tutor, , $internship] = $this->internship();

        $ended = $this->service()->startExit($internship, $tutor);

        $items = $ended->checklistItems()
            ->where('checklist_type', InternshipChecklistType::Sortie)
            ->orderBy('position')
            ->pluck('label')
            ->all();

        $this->assertSame(InternshipChecklistType::Sortie->items(), $items);
        $this->assertCount(5, $items);
        $this->assertSame(InternshipState::Termine, $ended->state);
        // La place du tuteur est libérée par la sortie (AC 23).
        $this->assertSame(0, app(TutorCapacityService::class)->currentLoad($tutor));
    }

    /**
     * AC 31 : le stagiaire consulte son dossier, le tuteur ceux de ses stagiaires, `direction`
     * tous ; tout autre accès est refusé.
     */
    public function test_ac_31_the_dossier_is_scoped_to_intern_tutor_and_direction(): void
    {
        [$tutor, $intern, $internship] = $this->internship();
        $otherTutor = User::factory()->active()->withRole('tuteur')->create();
        Internship::factory()->forTutor($otherTutor)->create();
        $direction = User::factory()->active()->withRole('direction')->create();
        $stranger = User::factory()->active()->withRole('employe')->create();

        $this->assertSame(1, Internship::query()->visibleTo($intern)->count());
        $this->assertSame(1, Internship::query()->visibleTo($tutor)->count());
        $this->assertSame(1, Internship::query()->visibleTo($otherTutor)->count());
        $this->assertSame(2, Internship::query()->visibleTo($direction)->count());
        $this->assertSame(0, Internship::query()->visibleTo($stranger)->count());

        // Le tuteur d'un autre stagiaire ne voit pas ce dossier-ci.
        $this->assertFalse(
            Internship::query()->visibleTo($otherTutor)->whereKey($internship->getKey())->exists(),
        );
    }

    /**
     * AC 32 : une évaluation validée n'est ni modifiable ni supprimable.
     */
    public function test_ac_32_a_validated_evaluation_is_frozen_and_cannot_be_deleted(): void
    {
        [$tutor, , $internship] = $this->internship();
        $week = CarbonImmutable::parse('2026-08-10', 'Africa/Niamey');
        $evaluation = $this->service()->recordWeekly($internship, $tutor, $week, $this->evaluationData());

        $validated = $this->service()->validate($evaluation, $tutor);

        $this->assertFalse($validated->isEditable());
        $this->assertNotNull($validated->validated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'internship_evaluation_validated']);

        // Plus aucune écriture sur la même semaine.
        try {
            $this->service()->recordWeekly($internship, $tutor, $week, $this->evaluationData('Tentative.'));
            $this->fail('Une évaluation validée ne doit plus accepter de modification.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('evaluation', $exception->errors());
        }

        // Ni seconde validation, ni suppression physique.
        $this->expectException(ValidationException::class);
        $this->service()->validate($validated->refresh(), $tutor);
    }

    public function test_ac_32_an_evaluation_can_never_be_physically_deleted(): void
    {
        [$tutor, , $internship] = $this->internship();
        $evaluation = $this->service()->recordWeekly(
            $internship,
            $tutor,
            CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'),
            $this->evaluationData(),
        );

        $this->expectException(\LogicException::class);
        $evaluation->delete();
    }

    /**
     * AC 33 : le bloc « Dernière évaluation » alimente le tableau de bord personnel.
     */
    public function test_ac_33_the_last_evaluation_block_feeds_the_personal_dashboard(): void
    {
        [$tutor, $intern, $internship] = $this->internship();

        // Avant toute évaluation, le bloc existe et explique ce qui va se passer.
        $empty = $this->service()->lastEvaluationBlock($intern);
        $this->assertIsArray($empty);
        $this->assertSame('Dernière évaluation', $empty['title']);
        $this->assertStringContainsString('Aucune évaluation', $empty['status']);

        $this->service()->recordWeekly(
            $internship,
            $tutor,
            CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'),
            $this->evaluationData(),
        );

        $block = $this->service()->lastEvaluationBlock($intern);
        $this->assertIsArray($block);
        $this->assertSame('Évaluation hebdomadaire', $block['status']);
        $this->assertStringContainsString($tutor->person->full_name, $block['detail']);
        $this->assertStringContainsString('/stages/'.$internship->getKey(), $block['action_url']);

        // Un compte sans stage n'a pas ce bloc.
        $this->assertNull($this->service()->lastEvaluationBlock(
            User::factory()->active()->withRole('employe')->create(),
        ));
    }

    private function service(): InternshipEvaluationService
    {
        return app(InternshipEvaluationService::class);
    }

    /** @return array{0: User, 1: User, 2: Internship} */
    private function internship(): array
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $intern = User::factory()->active()->withRole('stagiaire')->create();
        $internship = Internship::factory()->forTutor($tutor)->create(['user_id' => $intern->getKey()]);

        return [$tutor, $intern, $internship];
    }

    /** @return array{observed_progress: string, evidence: string|null, next_steps: string|null} */
    private function evaluationData(string $progress = 'Les procédures avancent au rythme prévu.'): array
    {
        return [
            'observed_progress' => $progress,
            'evidence' => 'Document partagé.',
            'next_steps' => 'Poursuivre sur le même rythme.',
        ];
    }

    private function completeConditions(Internship $internship, User $tutor): void
    {
        $this->service()->savePlan($internship, $tutor, [
            'skills_to_learn' => 'Rédaction.',
            'objectives' => 'Documenter.',
            'weekly_tasks' => 'Une par semaine.',
            'expected_evidence' => 'Document relu.',
        ]);
        $this->service()->recordWeekly(
            $internship,
            $tutor,
            CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'),
            $this->evaluationData(),
        );
    }
}
