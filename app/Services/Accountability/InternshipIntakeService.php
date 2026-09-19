<?php

namespace App\Services\Accountability;

use App\Enums\InternshipIntakeState;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\InternshipIntakeOutcome;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fiche d'entrée d'un stagiaire.
 *
 * L'approbation se fait en **une seule étape** par `direction` : aucun circuit multi-états n'est
 * implémenté en MVP (AC 15, C5). Les états possibles sont brouillon, soumise, puis approuvée ou
 * refusée — il n'existe aucun état intermédiaire de validation partielle.
 */
final class InternshipIntakeService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Crée ou mémorise la fiche en brouillon, avec ses résultats attendus.
     *
     * @param  array{candidate_user_id: int, manager_id: int, tutor_id: int, real_need: string, mission: string, duration_weeks: int, tools: string, outcomes: list<string>}  $data
     */
    public function draft(User $actor, array $data): InternshipIntakeForm
    {
        $form = new InternshipIntakeForm;

        return DB::connection($form->getConnectionName())->transaction(function () use ($actor, $data): InternshipIntakeForm {
            $form = new InternshipIntakeForm;
            $form->fill([
                'candidate_user_id' => $data['candidate_user_id'],
                'manager_id' => $data['manager_id'],
                'tutor_id' => $data['tutor_id'],
                'real_need' => $data['real_need'],
                'mission' => $data['mission'],
                'duration_weeks' => $data['duration_weeks'],
                'tools' => $data['tools'],
                'state' => InternshipIntakeState::Brouillon,
            ]);
            $form->saveOrFail();

            $this->replaceOutcomes($form, $data['outcomes']);

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $form,
                action: 'internship_intake_drafted',
                oldValues: null,
                newValues: ['candidate_user_id' => $form->candidate_user_id],
                reason: "Rédaction de la fiche d'entrée du stagiaire.",
            );

            return $form;
        });
    }

    /**
     * Soumet la fiche à `direction`. Une fiche portant moins de trois résultats attendus est
     * refusée côté serveur (AC 14).
     */
    public function submit(InternshipIntakeForm $form, User $actor): InternshipIntakeForm
    {
        if ($form->state !== InternshipIntakeState::Brouillon) {
            throw ValidationException::withMessages(['form' => 'Cette fiche est déjà soumise.']);
        }

        if ($form->tutor_id === null) {
            throw ValidationException::withMessages(['tutor_id' => 'Désignez le tuteur du stagiaire.']);
        }

        $this->assertRequiredOutcomes($form);

        return DB::connection($form->getConnectionName())->transaction(function () use ($form, $actor): InternshipIntakeForm {
            $locked = InternshipIntakeForm::query()->whereKey($form->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill([
                'state' => InternshipIntakeState::Soumise,
                'submitted_at' => CarbonImmutable::now('UTC'),
            ]);
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: 'internship_intake_submitted',
                oldValues: ['state' => InternshipIntakeState::Brouillon->value],
                newValues: ['state' => $locked->state->value],
                reason: "Fiche d'entrée soumise à la direction.",
            );

            return $locked;
        });
    }

    /**
     * Décision unique de `direction` : approbation ou refus, sans étape intermédiaire (AC 15).
     */
    public function decide(
        InternshipIntakeForm $form,
        User $actor,
        bool $approved,
        ?string $reason = null,
    ): InternshipIntakeForm {
        if ($form->state !== InternshipIntakeState::Soumise) {
            throw ValidationException::withMessages([
                'form' => 'Seule une fiche soumise reçoit une décision.',
            ]);
        }

        if (! $approved && trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'decision_reason' => 'Indiquez pourquoi la fiche est refusée.',
            ]);
        }

        $this->assertRequiredOutcomes($form);

        return DB::connection($form->getConnectionName())->transaction(function () use ($form, $actor, $approved, $reason): InternshipIntakeForm {
            $locked = InternshipIntakeForm::query()->whereKey($form->getKey())->lockForUpdate()->firstOrFail();
            $target = $approved ? InternshipIntakeState::Approuvee : InternshipIntakeState::Refusee;
            $locked->fill([
                'state' => $target,
                'decided_by' => $actor->getKey(),
                'decided_at' => CarbonImmutable::now('UTC'),
                'decision_reason' => $reason,
            ]);
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: $approved ? 'internship_intake_approved' : 'internship_intake_refused',
                oldValues: ['state' => InternshipIntakeState::Soumise->value],
                newValues: ['state' => $target->value],
                reason: $reason,
            );

            return $locked;
        });
    }

    /**
     * Trois résultats attendus au minimum, vérifiés côté serveur (AC 14).
     */
    public function assertRequiredOutcomes(InternshipIntakeForm $form): void
    {
        $count = $form->outcomes()->count();

        if ($count < InternshipIntakeForm::REQUIRED_OUTCOMES) {
            throw ValidationException::withMessages([
                'outcomes' => sprintf(
                    "Une fiche d'entrée porte au moins %d résultats attendus. Celle-ci en compte %d.",
                    InternshipIntakeForm::REQUIRED_OUTCOMES,
                    $count,
                ),
            ]);
        }
    }

    /**
     * @return LengthAwarePaginator<int, InternshipIntakeForm>
     */
    public function visibleFor(User $actor, int $perPage = 15): LengthAwarePaginator
    {
        return InternshipIntakeForm::query()
            ->visibleTo($actor)
            ->with(['candidate.person', 'tutor.person', 'manager.person', 'outcomes'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(InternshipIntakeForm $form): array
    {
        $form->loadMissing(['candidate.person', 'tutor.person', 'manager.person', 'outcomes', 'decider.person']);

        return [
            'id' => $form->getKey(),
            'candidate' => $this->labelFor($form->candidate),
            'manager' => $this->labelFor($form->manager),
            'tutor' => $form->tutor instanceof User ? $this->labelFor($form->tutor) : null,
            'real_need' => $form->real_need,
            'mission' => $form->mission,
            'duration_weeks' => $form->duration_weeks,
            'tools' => $form->tools,
            'state' => $form->state->value,
            'state_label' => $form->state->label(),
            'decided_by' => $form->decider instanceof User ? $this->labelFor($form->decider) : null,
            'decided_at' => $form->decided_at instanceof CarbonImmutable ? DateTimeFormatter::format($form->decided_at) : null,
            'decision_reason' => $form->decision_reason,
            'outcomes' => $form->outcomes->map(fn (InternshipIntakeOutcome $outcome): array => [
                'id' => $outcome->getKey(),
                'position' => $outcome->position,
                'description' => $outcome->description,
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $outcomes
     */
    private function replaceOutcomes(InternshipIntakeForm $form, array $outcomes): void
    {
        foreach ($outcomes as $index => $description) {
            $outcome = new InternshipIntakeOutcome;
            $outcome->fill([
                'internship_intake_form_id' => $form->getKey(),
                'position' => $index + 1,
                'description' => $description,
            ]);
            $outcome->saveOrFail();
        }
    }

    private function labelFor(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
