<?php

namespace App\Services\Accountability;

use App\Enums\InternshipChecklistType;
use App\Enums\InternshipIntakeState;
use App\Enums\InternshipState;
use App\Enums\UserState;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use App\Services\Identity\HierarchyService;
use App\Services\Identity\IdentityService;
use App\Services\Identity\InternActivationReadiness;
use App\Services\Identity\SessionRevocationService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cycle de vie du stage.
 *
 * L'activation du compte est une écriture dans `Identity` : elle passe donc par
 * `IdentityService::changeUserState()`, service propriétaire du compte, jamais par une écriture
 * directe depuis `Accountability` (source-tree.md, règle de couplage).
 */
final class InternshipService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IdentityService $identityService,
        private readonly InternActivationReadiness $readiness,
        private readonly TutorCapacityService $tutorCapacity,
        private readonly HierarchyService $hierarchy,
        private readonly SessionRevocationService $sessionRevocation,
    ) {}

    /**
     * Active le compte du stagiaire, ouvre son stage et génère sa checklist d'intégration.
     *
     * Les trois conditions de l'AC 16 sont vérifiées par `InternActivationReadiness`, dans
     * `Identity`. Le refus vient du service propriétaire du compte : cette méthode ne peut pas
     * le contourner.
     */
    public function activate(User $candidate, User $actor): Internship
    {
        if (! $candidate->hasRole('stagiaire')) {
            throw ValidationException::withMessages([
                'candidate' => "Ce compte n'est pas un compte de stagiaire.",
            ]);
        }

        if (Internship::query()->where('user_id', $candidate->getKey())->exists()) {
            throw ValidationException::withMessages([
                'candidate' => 'Ce stagiaire a déjà un stage ouvert.',
            ]);
        }

        $missing = $this->readiness->missingConditions($candidate);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'candidate' => sprintf(
                    'Ce compte de stagiaire ne peut pas être activé : %s.',
                    implode(', ', $missing),
                ),
            ]);
        }

        $form = InternshipIntakeForm::query()
            ->where('candidate_user_id', $candidate->getKey())
            ->where('state', InternshipIntakeState::Approuvee)
            ->orderByDesc('decided_at')
            ->firstOrFail();

        $internship = new Internship;

        return DB::connection($internship->getConnectionName())->transaction(function () use ($candidate, $actor, $form): Internship {
            // Verrou pessimiste sur la ligne du tuteur, pris avant toute écriture : deux
            // activations simultanées vers le même tuteur ne peuvent pas dépasser la limite
            // ensemble (AC 26).
            $this->tutorCapacity->lockTutorAndAssertCapacity((int) $form->tutor_id);

            // L'écriture sur le compte passe par le service propriétaire, qui vérifie une
            // seconde fois les trois conditions avant d'accepter le passage à `actif`.
            $this->identityService->changeUserState(
                user: $candidate,
                state: UserState::Actif,
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                reason: "Activation du stagiaire après approbation de sa fiche d'entrée.",
            );

            $internship = new Internship;
            $internship->fill([
                'user_id' => $candidate->getKey(),
                'internship_intake_form_id' => $form->getKey(),
                'tutor_id' => $form->tutor_id,
                'state' => InternshipState::Actif,
                'start_date' => CarbonImmutable::now('Africa/Niamey')->toDateString(),
            ]);
            $internship->saveOrFail();

            // Le tuteur devient le responsable direct du stagiaire (décision du propriétaire du
            // 2026-11-01) : visibilité et validation des objectifs reposent sur `manager_id`.
            $this->hierarchy->changeManager(
                user: $candidate,
                managerId: (int) $form->tutor_id,
                actor: $actor,
                reason: 'Le tuteur du stage devient le responsable direct du stagiaire.',
            );

            $this->generateChecklist($internship, InternshipChecklistType::Integration);

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $internship,
                action: 'internship_activated',
                oldValues: null,
                newValues: [
                    'user_id' => $internship->user_id,
                    'tutor_id' => $internship->tutor_id,
                    'internship_intake_form_id' => $form->getKey(),
                ],
                reason: "Ouverture du stage et génération de la checklist d'intégration.",
            );

            return $internship;
        });
    }

    /**
     * Réaffecte un stage à un autre tuteur.
     *
     * L'affectation est refusée si le tuteur visé a atteint la limite en vigueur, avec un message
     * qui le nomme et donne sa charge actuelle (AC 20). Le contrôle est pris sous verrou, comme à
     * l'activation (AC 26).
     */
    public function assignTutor(Internship $internship, User $tutor, User $actor): Internship
    {
        if ($internship->tutor_id === $tutor->getKey()) {
            return $internship;
        }

        if (! $internship->state->occupiesTutorSlot()) {
            throw ValidationException::withMessages([
                'tutor_id' => "Ce stage n'est plus actif : il n'a plus de tuteur à désigner.",
            ]);
        }

        return DB::connection($internship->getConnectionName())->transaction(function () use ($internship, $tutor, $actor): Internship {
            $this->tutorCapacity->lockTutorAndAssertCapacity((int) $tutor->getKey());

            $locked = Internship::query()->whereKey($internship->getKey())->lockForUpdate()->firstOrFail();
            $previousTutorId = $locked->tutor_id;
            $locked->tutor_id = (int) $tutor->getKey();
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: 'internship_tutor_assigned',
                oldValues: ['tutor_id' => $previousTutorId],
                newValues: ['tutor_id' => $locked->tutor_id],
                reason: 'Changement de tuteur du stage.',
            );

            $intern = User::query()->findOrFail($locked->user_id);
            $this->hierarchy->changeManager(
                user: $intern,
                managerId: (int) $locked->tutor_id,
                actor: $actor,
                reason: 'Changement de tuteur : le nouveau tuteur devient le responsable direct du stagiaire.',
            );

            return $locked;
        });
    }

    /**
     * Clôture un stage : la place occupée chez le tuteur est immédiatement libérée (AC 23).
     *
     * Décision du propriétaire du 2026-11-01 : si le compte n'a que le rôle `stagiaire`, son
     * accès est fermé dans la même transaction (état `termine`, sessions révoquées, audité).
     * Un stagiaire qui a aussi un autre rôle (ex. devenu employé) garde son accès.
     */
    public function end(Internship $internship, User $actor, InternshipState $state = InternshipState::Termine): Internship
    {
        if (! $internship->state->occupiesTutorSlot()) {
            throw ValidationException::withMessages(['internship' => 'Ce stage est déjà terminé.']);
        }

        return DB::connection($internship->getConnectionName())->transaction(function () use ($internship, $actor, $state): Internship {
            $locked = Internship::query()->whereKey($internship->getKey())->lockForUpdate()->firstOrFail();
            $previousState = $locked->state;
            $locked->fill([
                'state' => $state,
                'end_date' => CarbonImmutable::now('Africa/Niamey')->toDateString(),
                'ended_at' => CarbonImmutable::now('UTC'),
            ]);
            $locked->saveOrFail();

            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->labelFor($actor),
                auditable: $locked,
                action: 'internship_ended',
                oldValues: ['state' => $previousState->value],
                newValues: ['state' => $locked->state->value],
                reason: 'Fin du stage.',
            );

            $this->closeInternAccess($locked, $actor);

            return $locked;
        });
    }

    /**
     * Génère une checklist à partir des libellés de référence, sans doublon (AC 17, 30).
     */
    public function generateChecklist(Internship $internship, InternshipChecklistType $type): void
    {
        foreach ($type->items() as $index => $label) {
            $exists = InternshipChecklistItem::query()
                ->where('internship_id', $internship->getKey())
                ->where('checklist_type', $type)
                ->where('position', $index + 1)
                ->exists();

            if ($exists) {
                continue;
            }

            $item = new InternshipChecklistItem;
            $item->fill([
                'internship_id' => $internship->getKey(),
                'checklist_type' => $type,
                'position' => $index + 1,
                'label' => $label,
            ]);
            $item->saveOrFail();
        }
    }

    /**
     * Le compte du stagiaire est-il celui d'un stagiaire « pur », dont l'accès doit être fermé à la
     * fin du stage ?
     */
    public function accessClosesAtEnd(User $intern): bool
    {
        return $intern->getRoleNames()->values()->all() === ['stagiaire'];
    }

    private function closeInternAccess(Internship $internship, User $actor): void
    {
        $intern = User::query()->whereKey($internship->user_id)->lockForUpdate()->firstOrFail();

        if (! $this->accessClosesAtEnd($intern) || $intern->state !== UserState::Actif) {
            return;
        }

        $this->identityService->changeUserState(
            user: $intern,
            state: UserState::Termine,
            actorId: $actor->getKey(),
            actorLabel: $this->labelFor($actor),
            reason: "Fin du stage : l'accès du stagiaire à l'application est fermé.",
        );

        $revoked = $this->sessionRevocation->revokeFor($intern);

        $this->auditLogger->record(
            actorId: $actor->getKey(),
            actorLabel: $this->labelFor($actor),
            auditable: $intern,
            action: 'intern_access_closed',
            oldValues: ['state' => UserState::Actif->value],
            newValues: [
                'state' => UserState::Termine->value,
                'sessions_revoked' => $revoked,
                'internship_id' => $internship->getKey(),
            ],
            reason: 'Fin du stage : compte du stagiaire fermé, sessions révoquées. Les données sont conservées.',
        );
    }

    private function labelFor(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
