<?php

namespace Tests\Feature\Http;

use App\Enums\InternshipState;
use App\Models\Accountability\Internship;
use App\Models\Identity\User;
use App\Services\Accountability\InternshipEvaluationService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Task 6 — parcours HTTP du dossier de stage (AC 27 à 33).
 */
class InternshipDossierHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    /**
     * AC 31 : un pair sans lien avec le stage est refusé par URL directe, en lecture comme en
     * écriture.
     */
    public function test_ac_31_an_unrelated_account_is_refused_by_direct_url(): void
    {
        [$tutor, , $internship] = $this->internship();
        $stranger = User::factory()->active()->withRole('employe')->create();

        $this->flushSession();
        $this->actingAs($stranger)->get(route('internships.show', $internship))->assertForbidden();
        $this->actingAs($stranger)
            ->post(route('internships.evaluations.store', $internship), $this->evaluationPayload())
            ->assertForbidden();

        // Un autre tuteur n'accède pas non plus à ce dossier.
        $otherTutor = User::factory()->active()->withRole('tuteur')->create();
        $this->flushSession();
        $this->actingAs($otherTutor)->get(route('internships.show', $internship))->assertForbidden();

        $this->flushSession();
        $this->actingAs($tutor)->get(route('internships.show', $internship))->assertOk();
    }

    public function test_ac_28_the_intern_reads_their_dossier_but_does_not_manage_it(): void
    {
        [$tutor, $intern, $internship] = $this->internship();
        app(InternshipEvaluationService::class)->recordWeekly(
            $internship,
            $tutor,
            CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'),
            ['observed_progress' => 'Bon rythme.', 'evidence' => null, 'next_steps' => null],
        );

        $this->actingAs($intern)
            ->get(route('internships.show', $internship))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Accountability/Internships/Show')
                ->has('internship.evaluations', 1)
                ->where('internship.evaluations.0.observed_progress', 'Bon rythme.')
                ->where('permissions.manage', false));
    }

    public function test_ac_27_the_tutor_saves_the_internship_plan(): void
    {
        [$tutor, , $internship] = $this->internship();

        $this->actingAs($tutor)
            ->put(route('internships.plan.save', $internship), [
                'skills_to_learn' => 'Rédaction.',
                'objectives' => 'Documenter.',
                'weekly_tasks' => 'Une procédure par semaine.',
                'expected_evidence' => 'Document relu.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('internship_plans', 1);
    }

    public function test_ac_28_a_weekly_evaluation_requires_its_week(): void
    {
        [$tutor, , $internship] = $this->internship();

        $payload = $this->evaluationPayload();
        unset($payload['week_start_date']);

        $this->actingAs($tutor)
            ->from(route('internships.show', $internship))
            ->post(route('internships.evaluations.store', $internship), $payload)
            ->assertSessionHasErrors('week_start_date');
    }

    /**
     * AC 32 : une évaluation validée refuse toute écriture ultérieure, y compris par HTTP.
     */
    public function test_ac_32_a_validated_evaluation_refuses_further_writes(): void
    {
        [$tutor, , $internship] = $this->internship();

        $this->actingAs($tutor)
            ->post(route('internships.evaluations.store', $internship), $this->evaluationPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $evaluation = $internship->evaluations()->firstOrFail();

        $this->actingAs($tutor)
            ->patch(route('internships.evaluations.validate', [$internship, $evaluation]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($evaluation->refresh()->validated_at);

        $this->actingAs($tutor)
            ->from(route('internships.show', $internship))
            ->post(route('internships.evaluations.store', $internship), $this->evaluationPayload('Tentative tardive.'))
            ->assertSessionHasErrors('evaluation');

        $this->assertSame('Les procédures avancent.', $evaluation->refresh()->observed_progress);
    }

    public function test_ac_30_ending_the_internship_generates_the_exit_checklist(): void
    {
        [$tutor, , $internship] = $this->internship();

        $this->actingAs($tutor)
            ->patch(route('internships.exit', $internship))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(InternshipState::Termine, $internship->refresh()->state);
        $this->assertSame(5, $internship->checklistItems()->where('checklist_type', 'sortie')->count());
    }

    /**
     * AC 33 : le tableau de bord personnel porte le bloc « Dernière évaluation » pour le
     * stagiaire, et pas pour un compte sans stage.
     */
    public function test_ac_33_the_home_dashboard_carries_the_last_evaluation_block(): void
    {
        [$tutor, $intern, $internship] = $this->internship();
        app(InternshipEvaluationService::class)->recordWeekly(
            $internship,
            $tutor,
            CarbonImmutable::parse('2026-08-10', 'Africa/Niamey'),
            ['observed_progress' => 'Bon rythme.', 'evidence' => null, 'next_steps' => null],
        );

        $this->actingAs($intern)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('lastEvaluation.title', 'Dernière évaluation')
                ->where('lastEvaluation.status', 'Évaluation hebdomadaire'));

        $this->flushSession();
        $this->actingAs(User::factory()->active()->withRole('employe')->create())
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('lastEvaluation', null));
    }

    /** @return array{0: User, 1: User, 2: Internship} */
    private function internship(): array
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $intern = User::factory()->active()->withRole('stagiaire')->create();
        $internship = Internship::factory()->forTutor($tutor)->create(['user_id' => $intern->getKey()]);

        return [$tutor, $intern, $internship];
    }

    /** @return array<string, mixed> */
    private function evaluationPayload(string $progress = 'Les procédures avancent.'): array
    {
        return [
            'type' => 'hebdomadaire',
            'week_start_date' => '2026-08-10',
            'observed_progress' => $progress,
            'evidence' => null,
            'next_steps' => null,
        ];
    }
}
