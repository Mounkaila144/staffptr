<?php

namespace Tests\Feature;

use App\Enums\AlertLevel;
use App\Enums\InternshipChecklistType;
use App\Enums\InternshipIntakeState;
use App\Enums\InternshipState;
use App\Enums\UserState;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Accountability\InternshipIntakeService;
use App\Services\Accountability\InternshipService;
use App\Services\Finance\AlertLevelService;
use App\Services\Identity\AccountAdministrationService;
use App\Services\Identity\IdentityService;
use App\Services\Identity\InternActivationReadiness;
use Database\Seeders\SettingSeeder;
use Illuminate\Validation\ValidationException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Task 4 — fiche d'entrée, approbation unique et activation du stagiaire (AC 14 à 19, 41).
 */
class InternActivationTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // L'activation lit la limite de stagiaires par tuteur dans le paramétrage (AC 22).
        $this->seed(SettingSeeder::class);
    }

    public function test_ac_14_an_intake_form_carries_its_three_required_outcomes(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();

        $form = $this->intake()->draft($direction, $this->formData($candidate, $tutor, $direction));

        $this->assertSame(3, $form->outcomes()->count());
        $this->assertSame(InternshipIntakeState::Brouillon, $form->state);
        $this->assertSame('Renfort sur la documentation.', $form->real_need);
        $this->assertSame(12, $form->duration_weeks);
        $this->assertSame($tutor->getKey(), $form->tutor_id);
    }

    public function test_ac_14_a_submission_with_fewer_than_three_outcomes_is_refused(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $data = $this->formData($candidate, $tutor, $direction);
        $data['outcomes'] = ['Un seul résultat.', 'Un deuxième.'];
        $form = $this->intake()->draft($direction, $data);

        try {
            $this->intake()->submit($form, $direction);
            $this->fail('Une fiche de moins de trois résultats doit être refusée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('outcomes', $exception->errors());
            $this->assertStringContainsString('au moins 3 résultats', $exception->errors()['outcomes'][0]);
        }

        $this->assertSame(InternshipIntakeState::Brouillon, $form->refresh()->state);
    }

    /**
     * AC 15 : la décision est unique. Aucun état intermédiaire n'existe entre « soumise » et la
     * décision, et une fiche déjà décidée n'en reçoit pas de seconde.
     */
    public function test_ac_15_approval_is_a_single_step_reserved_to_direction(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $form = $this->submittedForm($direction, $tutor, $candidate);

        $approved = $this->intake()->decide($form, $direction, approved: true);

        $this->assertSame(InternshipIntakeState::Approuvee, $approved->state);
        $this->assertSame($direction->getKey(), $approved->decided_by);
        $this->assertNotNull($approved->decided_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'internship_intake_approved']);

        // Le circuit ne comporte que quatre états : ni « en cours de validation », ni double visa.
        $this->assertSame(
            ['brouillon', 'soumise', 'approuvee', 'refusee'],
            array_map(fn (InternshipIntakeState $state): string => $state->value, InternshipIntakeState::cases()),
        );

        $this->expectException(ValidationException::class);
        $this->intake()->decide($approved->refresh(), $direction, approved: true);
    }

    /**
     * AC 16 et 41, condition 1 isolée : sans fiche approuvée, l'activation est refusée alors même
     * que le tuteur et les trois objectifs sont en place.
     */
    public function test_ac_16_condition_one_alone_approved_intake_form_is_missing(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->submittedForm($direction, $tutor, $candidate);
        $this->giveObjectives($candidate, 3);

        $readiness = $this->readiness();
        $this->assertFalse($readiness->hasApprovedIntakeForm($candidate));
        $this->assertTrue($readiness->hasRequiredObjectives($candidate));

        $this->assertActivationRefusedBecause($candidate, $direction, "la fiche d'entrée n'est pas approuvée");
    }

    /**
     * AC 16 et 41, condition 2 isolée : sans tuteur désigné, l'activation est refusée alors même
     * que la fiche est approuvée et les trois objectifs enregistrés.
     */
    public function test_ac_16_condition_two_alone_designated_tutor_is_missing(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $form = $this->approvedForm($direction, $tutor, $candidate);
        $this->giveObjectives($candidate, 3);

        // Le tuteur est retiré de la fiche approuvée : les deux autres conditions restent tenues.
        InternshipIntakeForm::query()->whereKey($form->getKey())->update(['tutor_id' => null]);

        $readiness = $this->readiness();
        $this->assertFalse($readiness->hasDesignatedTutor($candidate));
        $this->assertTrue($readiness->hasApprovedIntakeForm($candidate));
        $this->assertTrue($readiness->hasRequiredObjectives($candidate));

        $this->assertActivationRefusedBecause($candidate, $direction, "aucun tuteur n'est désigné");
    }

    /**
     * AC 16 et 41, condition 3 isolée : avec deux objectifs seulement, l'activation est refusée
     * alors même que la fiche est approuvée et le tuteur désigné.
     */
    public function test_ac_16_condition_three_alone_three_objectives_are_missing(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->approvedForm($direction, $tutor, $candidate);
        $this->giveObjectives($candidate, 2);

        $readiness = $this->readiness();
        $this->assertTrue($readiness->hasApprovedIntakeForm($candidate));
        $this->assertTrue($readiness->hasDesignatedTutor($candidate));
        $this->assertFalse($readiness->hasRequiredObjectives($candidate));
        $this->assertSame(2, $readiness->objectiveCount($candidate));

        $this->assertActivationRefusedBecause($candidate, $direction, '2 objectifs sont enregistrés sur les 3 attendus');
    }

    public function test_ac_16_the_three_conditions_together_allow_activation(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->approvedForm($direction, $tutor, $candidate);
        $this->giveObjectives($candidate, 3);

        $this->assertTrue($this->readiness()->isSatisfiedBy($candidate));

        $internship = $this->internships()->activate($candidate, $direction);

        $this->assertSame(UserState::Actif, $candidate->refresh()->state);
        $this->assertSame(InternshipState::Actif, $internship->state);
        $this->assertSame($tutor->getKey(), $internship->tutor_id);
    }

    /**
     * AC 17 : la checklist d'intégration est générée à l'activation, avec ses six éléments.
     */
    public function test_ac_17_the_integration_checklist_is_generated_on_activation(): void
    {
        $internship = $this->activatedInternship();

        $items = $internship->checklistItems()
            ->where('checklist_type', InternshipChecklistType::Integration)
            ->orderBy('position')
            ->pluck('label')
            ->all();

        $this->assertSame(InternshipChecklistType::Integration->items(), $items);
        $this->assertCount(6, $items);
    }

    /**
     * AC 18 : le point de contrôle du niveau d'alerte est posé au bon endroit et laisse passer
     * l'activation tant qu'aucune charge fixe n'est paramétrée — l'assiette est alors nulle et les
     * encaissements l'atteignent toujours.
     *
     * Le calcul réel est livré par l'Epic 9 ; le test bloquant du niveau rouge vit dans
     * `AlertLevelEffectsTest`.
     */
    public function test_ac_18_the_alert_level_checkpoint_lets_activation_through_without_fixed_charges(): void
    {
        $service = app(AlertLevelService::class);

        $this->assertSame(AlertLevel::Vert, $service->current());
        $this->assertTrue($service->allowsAccountActivation());
        $this->assertTrue($this->readiness()->isAllowedByAlertLevel());
        // Seul le niveau rouge bloque une activation ; l'orange ne bloque rien.
        $this->assertTrue(AlertLevel::Rouge->blocksAccountActivation());
        $this->assertFalse(AlertLevel::Orange->blocksAccountActivation());
    }

    public function test_ac_19_activation_produces_an_audit_entry(): void
    {
        $internship = $this->activatedInternship();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $internship->getKey(),
            'action' => 'internship_activated',
        ]);
        // L'écriture sur le compte est auditée par le service propriétaire d'`Identity`.
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $internship->user_id,
            'action' => 'user_state_changed',
        ]);
    }

    /**
     * Le garde-fou vit dans le service propriétaire du compte : il est donc impossible de
     * l'esquiver en passant directement par `Identity`.
     */
    public function test_ac_41_the_guard_cannot_be_bypassed_through_identity(): void
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->submittedForm($direction, $tutor, $candidate);

        try {
            app(IdentityService::class)->changeUserState(
                user: $candidate,
                state: UserState::Actif,
                actorId: $direction->getKey(),
                actorLabel: 'Direction',
                reason: 'Tentative de contournement.',
            );
            $this->fail('Le service propriétaire du compte doit refuser cette activation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('state', $exception->errors());
        }

        $this->assertSame(UserState::Invite, $candidate->refresh()->state);
    }

    public function test_ac_41_an_intern_account_is_never_created_already_active(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $created = app(AccountAdministrationService::class)->create($direction, [
            'person_mode' => 'new',
            'full_name' => 'Nouvelle stagiaire',
            'first_seen_at' => '2026-08-16',
            'phone' => '+22790000042',
            'roles' => ['stagiaire'],
        ]);

        $this->assertSame(UserState::Invite, $created['user']->state);
    }

    private function assertActivationRefusedBecause(User $candidate, User $actor, string $expectedReason): void
    {
        try {
            $this->internships()->activate($candidate, $actor);
            $this->fail("L'activation doit être refusée : {$expectedReason}.");
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($expectedReason, $exception->errors()['candidate'][0]);
        }

        $this->assertSame(UserState::Invite, $candidate->refresh()->state);
        $this->assertSame(0, Internship::query()->where('user_id', $candidate->getKey())->count());
    }

    private function activatedInternship(): Internship
    {
        [$direction, $tutor, $candidate] = $this->actors();
        $this->approvedForm($direction, $tutor, $candidate);
        $this->giveObjectives($candidate, 3);

        return $this->internships()->activate($candidate, $direction);
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function actors(): array
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $candidate = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);

        return [$direction, $tutor, $candidate];
    }

    private function submittedForm(User $direction, User $tutor, User $candidate): InternshipIntakeForm
    {
        $form = $this->intake()->draft($direction, $this->formData($candidate, $tutor, $direction));

        return $this->intake()->submit($form, $direction);
    }

    private function approvedForm(User $direction, User $tutor, User $candidate): InternshipIntakeForm
    {
        return $this->intake()->decide($this->submittedForm($direction, $tutor, $candidate), $direction, approved: true);
    }

    private function giveObjectives(User $candidate, int $count): void
    {
        Objective::factory()->count($count)->create(['user_id' => $candidate->getKey()]);
    }

    /**
     * @return array{candidate_user_id: int, manager_id: int, tutor_id: int, real_need: string, mission: string, duration_weeks: int, tools: string, outcomes: list<string>}
     */
    private function formData(User $candidate, User $tutor, User $manager): array
    {
        return [
            'candidate_user_id' => (int) $candidate->getKey(),
            'manager_id' => (int) $manager->getKey(),
            'tutor_id' => (int) $tutor->getKey(),
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

    private function intake(): InternshipIntakeService
    {
        return app(InternshipIntakeService::class);
    }

    private function internships(): InternshipService
    {
        return app(InternshipService::class);
    }

    private function readiness(): InternActivationReadiness
    {
        return app(InternActivationReadiness::class);
    }
}
