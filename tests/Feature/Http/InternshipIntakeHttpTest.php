<?php

namespace Tests\Feature\Http;

use App\Enums\InternshipIntakeState;
use App\Enums\UserState;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\InternshipIntakeService;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 4 — parcours HTTP de la fiche d'entrée et de l'activation (AC 14 à 19, 41).
 */
class InternshipIntakeHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // L'activation lit la limite de stagiaires par tuteur dans le paramétrage (AC 22).
        $this->seed(SettingSeeder::class);
    }

    public function test_ac_14_the_form_request_refuses_fewer_than_three_outcomes(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();

        $payload = $this->payload($candidate, $tutor, $direction);
        $payload['outcomes'] = ['Un seul résultat.', 'Un deuxième.'];

        $this->actingAs($direction)
            ->from(route('internship-intakes.index'))
            ->post(route('internship-intakes.store'), $payload)
            ->assertSessionHasErrors('outcomes');

        $this->assertDatabaseCount('internship_intake_forms', 0);
    }

    public function test_ac_14_a_complete_form_is_recorded_with_its_three_outcomes(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();

        $this->actingAs($direction)
            ->post(route('internship-intakes.store'), $this->payload($candidate, $tutor, $direction))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('internship_intake_forms', 1);
        $this->assertDatabaseCount('internship_intake_outcomes', 3);
    }

    /**
     * AC 15 : seule `direction` décide, et en une seule étape. Un tuteur rédige et soumet mais
     * ne décide pas.
     */
    public function test_ac_15_only_direction_decides_on_a_submitted_form(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $form = $this->submittedForm($direction, $tutor, $candidate);

        $this->flushSession();
        $this->actingAs($tutor)
            ->patch(route('internship-intakes.decide', $form), ['approved' => true])
            ->assertForbidden();

        $this->assertSame(InternshipIntakeState::Soumise, $form->refresh()->state);

        $this->flushSession();
        $this->actingAs($direction)
            ->patch(route('internship-intakes.decide', $form), ['approved' => true])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(InternshipIntakeState::Approuvee, $form->refresh()->state);
    }

    public function test_ac_15_a_refusal_requires_its_reason(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $form = $this->submittedForm($direction, $tutor, $candidate);

        $this->actingAs($direction)
            ->from(route('internship-intakes.show', $form))
            ->patch(route('internship-intakes.decide', $form), ['approved' => false])
            ->assertSessionHasErrors('decision_reason');

        $this->assertSame(InternshipIntakeState::Soumise, $form->refresh()->state);
    }

    /**
     * AC 16 : l'activation par la route est refusée tant que les trois conditions ne sont pas
     * réunies, et le compte reste `invite`.
     */
    public function test_ac_16_activation_is_refused_until_the_three_conditions_are_met(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->submittedForm($direction, $tutor, $candidate);

        $this->actingAs($direction)
            ->from(route('internship-intakes.index'))
            ->post(route('interns.activate', $candidate))
            ->assertSessionHasErrors('candidate');

        $this->assertSame(UserState::Invite, $candidate->refresh()->state);
        $this->assertDatabaseCount('internships', 0);
    }

    public function test_ac_16_to_19_activation_succeeds_and_generates_the_checklist(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->approvedForm($direction, $tutor, $candidate);
        Objective::factory()->count(3)->create(['user_id' => $candidate->getKey()]);

        $this->actingAs($direction)
            ->post(route('interns.activate', $candidate))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(UserState::Actif, $candidate->refresh()->state);
        $this->assertDatabaseCount('internships', 1);
        $this->assertDatabaseCount('internship_checklist_items', 6);
        $this->assertDatabaseHas('audit_logs', ['action' => 'internship_activated']);
    }

    public function test_ac_16_the_show_page_states_the_three_conditions_one_by_one(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $form = $this->approvedForm($direction, $tutor, $candidate);
        Objective::factory()->count(2)->create(['user_id' => $candidate->getKey()]);

        $this->actingAs($direction)
            ->get(route('internship-intakes.show', $form))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Accountability/Internships/Intakes/Show')
                ->where('readiness.approved_intake_form', true)
                ->where('readiness.designated_tutor', true)
                ->where('readiness.required_objectives', false)
                ->where('readiness.objective_count', 2)
                ->where('readiness.alert_level_allows', true)
                ->where('readiness.satisfied', false));
    }

    public function test_ac_31_an_unrelated_account_is_refused_by_direct_url(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $form = $this->approvedForm($direction, $tutor, $candidate);
        $otherIntern = User::factory()->active()->withRole('stagiaire')->create();

        $this->flushSession();
        $this->actingAs($otherIntern)->get(route('internship-intakes.show', $form))->assertForbidden();

        // Le stagiaire concerné, lui, consulte bien sa propre fiche.
        $this->flushSession();
        // Le compte vient d'être activé : il a déjà changé son mot de passe provisoire.
        $candidate->forceFill(['state' => UserState::Actif, 'must_change_password' => false])->save();
        $this->actingAs($candidate)->get(route('internship-intakes.show', $form))->assertOk();
    }

    /**
     * La route `store` existait et passait ses tests serveur, mais aucun écran ne l'appelait :
     * le parcours s'arrêtait avant même de commencer. L'écran des fiches doit donc porter les
     * trois listes nécessaires à la rédaction.
     */
    public function test_the_intake_screen_carries_the_lists_needed_to_draft_a_form(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();

        $this->actingAs($direction)->get(route('internship-intakes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Accountability/Internships/Intakes/Index')
                ->where('canCreate', true)
                ->has('candidates', 1)
                // L'état du compte est écrit dans le libellé : la direction voit d'un coup d'œil
                // lequel de ses stagiaires attend encore son activation.
                ->where('candidates.0.value', (int) $candidate->getKey())
                ->where('candidates.0.label', $candidate->person->full_name.' — Invité')
                ->has('tutors')
                ->has('managers'));

        $tutorIds = array_column(
            $this->actingAs($direction)->get(route('internship-intakes.index'))->inertiaPage()['props']['tutors'],
            'value',
        );
        $this->assertContains((int) $tutor->getKey(), $tutorIds);
        $this->assertContains((int) $direction->getKey(), $tutorIds);
    }

    /** Un compte qui ne fait que consulter ne reçoit pas l'annuaire dans ses props. */
    public function test_a_reader_without_the_management_permission_receives_no_directory(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->approvedForm($direction, $tutor, $candidate);
        $candidate->forceFill(['state' => UserState::Actif, 'must_change_password' => false])->save();

        $this->flushSession();
        $this->actingAs($candidate)->get(route('internship-intakes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreate', false)
                ->has('candidates', 0)
                ->has('managers', 0)
                ->has('tutors', 0));
    }

    /** Le parcours complet, de la rédaction à l'activation, sans passer par le service. */
    public function test_the_whole_path_runs_from_the_screen_up_to_the_activation(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();

        $this->actingAs($direction)
            ->post(route('internship-intakes.store'), $this->payload($candidate, $tutor, $direction))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $form = InternshipIntakeForm::query()->firstOrFail();
        $this->actingAs($direction)->patch(route('internship-intakes.submit', $form))->assertRedirect();
        $this->actingAs($direction)->patch(route('internship-intakes.decide', $form), [
            'approved' => true,
            'decision_reason' => 'Besoin réel confirmé.',
        ])->assertRedirect();

        // Tant que les trois objectifs manquent, l'écran refuse d'ouvrir la porte.
        $this->actingAs($direction)->get(route('internship-intakes.show', $form))
            ->assertInertia(fn (Assert $page) => $page
                ->where('readiness.satisfied', false)
                ->where('permissions.activate', true));

        // Les objectifs passent par l'écran, pas par la factory : c'est la validation HTTP qui
        // refusait de désigner un compte encore invité, et une fixture posée en base masquait
        // exactement le blocage que ce parcours doit prouver.
        for ($rank = 1; $rank <= 3; $rank++) {
            $this->actingAs($direction)->post('/objectifs', [
                'user_id' => (int) $candidate->getKey(),
                'title' => "Résultat attendu {$rank}",
                'description' => 'Objectif du stage, convenu avec le tuteur.',
                'indicator' => 'Avancement constaté en revue',
                'target_value' => '100 %',
                'expected_evidence' => 'Livrable déposé et relu.',
                'due_date' => now()->addDays(20 + $rank)->format('Y-m-d'),
                'priority' => 'normale',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Objective::query()->where('user_id', $candidate->getKey())->count());

        $this->actingAs($direction)->get(route('internship-intakes.show', $form))
            ->assertInertia(fn (Assert $page) => $page->where('readiness.satisfied', true));
        $this->actingAs($direction)->post(route('interns.activate', $candidate))->assertRedirect();

        $this->assertSame(UserState::Actif, $candidate->refresh()->state);
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function actors(): array
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $candidate = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);

        return [$direction, $tutor, $candidate];
    }

    /** @return array<string, mixed> */
    private function payload(User $candidate, User $tutor, User $manager): array
    {
        return [
            'candidate_user_id' => $candidate->getKey(),
            'manager_id' => $manager->getKey(),
            'tutor_id' => $tutor->getKey(),
            'real_need' => 'Renfort sur la documentation.',
            'mission' => 'Rédiger les procédures du module de suivi.',
            'duration_weeks' => 12,
            'tools' => 'Poste de travail et accès à PTR Staff.',
            'outcomes' => [
                'Trois procédures rédigées.',
                'Un guide de prise en main livré.',
                'Une restitution tenue.',
            ],
        ];
    }

    private function submittedForm(User $direction, User $tutor, User $candidate): InternshipIntakeForm
    {
        $service = app(InternshipIntakeService::class);
        /** @var array{candidate_user_id: int, manager_id: int, tutor_id: int, real_need: string, mission: string, duration_weeks: int, tools: string, outcomes: list<string>} $data */
        $data = $this->payload($candidate, $tutor, $direction);

        return $service->submit($service->draft($direction, $data), $direction);
    }

    private function approvedForm(User $direction, User $tutor, User $candidate): InternshipIntakeForm
    {
        return app(InternshipIntakeService::class)->decide(
            $this->submittedForm($direction, $tutor, $candidate),
            $direction,
            approved: true,
        );
    }
}
