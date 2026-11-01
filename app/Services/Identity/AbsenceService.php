<?php

namespace App\Services\Identity;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Notifications\AbsenceDecidedNotification;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AbsenceService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AttachmentService $attachmentService,
        private readonly AbsenceDecisionRule $decisionRule,
    ) {}

    public function declare(
        User $user,
        AbsenceType $type,
        string $startDate,
        string $endDate,
        string $reason,
        ?Attachment $attachment,
        User $actor,
    ): Absence {
        if ((int) $user->getKey() !== (int) $actor->getKey()) {
            throw new AuthorizationException('Une absence ne peut être déclarée que pour soi-même.');
        }

        if (CarbonImmutable::parse($endDate)->lessThan(CarbonImmutable::parse($startDate))) {
            throw ValidationException::withMessages([
                'end_date' => 'La date de fin doit être égale ou postérieure à la date de début.',
            ]);
        }

        $absence = new Absence;

        return DB::connection($absence->getConnectionName())->transaction(function () use (
            $absence,
            $user,
            $type,
            $startDate,
            $endDate,
            $reason,
            $attachment,
            $actor,
        ): Absence {
            $lockedAttachment = null;

            if ($attachment instanceof Attachment) {
                $lockedAttachment = Attachment::query()->whereKey($attachment->getKey())->lockForUpdate()->firstOrFail();
                $this->assertStagedForActor($lockedAttachment, $actor);
            }

            $absence->fill([
                'user_id' => $user->getKey(),
                'type' => $type,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
                'state' => AbsenceState::Demandee,
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $absence,
                operation: fn (): bool => $absence->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'absence_requested',
                newValues: [
                    ...$absence->getAttributes(),
                    'attachment_ulid' => $lockedAttachment?->ulid,
                ],
            );

            if ($lockedAttachment instanceof Attachment) {
                $this->attachmentService->attachExisting($lockedAttachment, $absence, $actor);
            }

            return $absence->load(['user.person', 'user.manager.person', 'attachment']);
        });
    }

    public function approve(Absence $absence, User $actor): Absence
    {
        return $this->notifyRequester($this->decide($absence, $actor, AbsenceState::Approuvee, null, 'absence_approved'));
    }

    public function refuse(Absence $absence, string $reason, User $actor): Absence
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'decision_reason' => 'Expliquez brièvement pourquoi la demande est refusée.',
            ]);
        }

        return $this->notifyRequester($this->decide($absence, $actor, AbsenceState::Refusee, $reason, 'absence_refused'));
    }

    public function cancel(Absence $absence, User $actor): Absence
    {
        return DB::connection($absence->getConnectionName())->transaction(function () use ($absence, $actor): Absence {
            $lockedAbsence = $this->locked($absence);

            if ((int) $lockedAbsence->user_id !== (int) $actor->getKey()) {
                throw new AuthorizationException('Seul le demandeur peut annuler cette absence.');
            }

            $this->assertPending($lockedAbsence);

            return $this->transition(
                $lockedAbsence,
                $actor,
                AbsenceState::Annulee,
                null,
                'absence_cancelled',
            );
        });
    }

    private function decide(
        Absence $absence,
        User $actor,
        AbsenceState $state,
        ?string $reason,
        string $action,
    ): Absence {
        return DB::connection($absence->getConnectionName())->transaction(function () use (
            $absence,
            $actor,
            $state,
            $reason,
            $action,
        ): Absence {
            $lockedAbsence = $this->locked($absence);
            $this->assertCanDecide($lockedAbsence, $actor);
            $this->assertPending($lockedAbsence);

            return $this->transition($lockedAbsence, $actor, $state, $reason, $action);
        });
    }

    private function transition(
        Absence $absence,
        User $actor,
        AbsenceState $state,
        ?string $reason,
        string $action,
    ): Absence {
        $oldValues = Arr::only($absence->getRawOriginal(), ['state', 'decision_reason', 'decided_by', 'decided_at']);
        $absence->fill([
            'state' => $state,
            'decision_reason' => $reason,
            'decided_by' => $actor->getKey(),
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);
        $newValues = Arr::only($absence->getAttributes(), ['state', 'decision_reason', 'decided_by', 'decided_at']);

        $this->auditLogger->runExplicitly(
            auditable: $absence,
            operation: fn (): bool => $absence->saveOrFail(),
            actorId: $actor->getKey(),
            actorLabel: $this->actorLabel($actor),
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
        );

        return $absence->refresh()->load(['user.person', 'user.manager.person', 'decidedBy.person', 'attachment']);
    }

    /**
     * Le responsable direct actif décide ; sans responsable direct actif, tout compte direction
     * actif autre que le demandeur décide (décision du propriétaire du 2026-11-01).
     */
    private function assertCanDecide(Absence $absence, User $actor): void
    {
        if ((int) $absence->user_id === (int) $actor->getKey()) {
            throw new AuthorizationException("L'auto-approbation d'une absence est interdite.");
        }

        if ($this->decisionRule->canDecide($actor, $absence)) {
            return;
        }

        throw new AuthorizationException($this->decisionRule->isDecidedByDirection($absence)
            ? 'Sans responsable direct, seule la direction peut décider de cette absence.'
            : 'Seul le responsable direct peut décider de cette absence.');
    }

    private function assertPending(Absence $absence): void
    {
        if (! $absence->isPending()) {
            throw ValidationException::withMessages([
                'state' => 'Cette demande a déjà reçu une décision et ne peut plus être modifiée.',
            ]);
        }
    }

    private function locked(Absence $absence): Absence
    {
        return Absence::query()
            ->with(['user.person', 'user.manager'])
            ->whereKey($absence->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertStagedForActor(Attachment $attachment, User $actor): void
    {
        if ($attachment->getAttribute('attachable_type') !== $actor->person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $actor->person_id
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages([
                'attachment_ulid' => 'Ce justificatif ne peut pas être rattaché à cette demande.',
            ]);
        }
    }

    /**
     * Prévient le demandeur de la décision, après validation de la transaction. La cloche est
     * écrite tout de suite ; WhatsApp passe par la file et ne peut pas faire échouer la décision.
     */
    private function notifyRequester(Absence $absence): Absence
    {
        Notification::sendNow($absence->user, AbsenceDecidedNotification::forDatabase($absence), ['database']);
        $absence->user->notify(AbsenceDecidedNotification::forWhatsApp($absence));

        return $absence;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
