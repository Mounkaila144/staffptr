<?php

namespace App\Services\Finance;

use App\Enums\CapitalContributionState;
use App\Models\Finance\CapitalContribution;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apports d'argent personnel des directeurs (story 12.1, AC 7 à 11).
 *
 * L'enregistrement vaut accord de son auteur ; l'apport n'émet de parts qu'une fois approuvé par
 * **l'autre** directeur. Aucune opération de remboursement n'existe ici, et c'est délibéré : la
 * direction a tranché que l'apport est définitif. Ajouter une telle écriture contredirait la
 * décision métier et la nature même de ces parts.
 */
final readonly class CapitalContributionService
{
    public function __construct(
        private ContributionShareService $contributionShares,
        private AuditLogger $auditLogger,
    ) {}

    public function record(int $amount, string $purpose, string $idempotencyKey, User $actor): CapitalContribution
    {
        $this->assertDirector($actor, 'Seul un directeur peut enregistrer un apport d’argent personnel.');

        return DB::transaction(function () use ($amount, $purpose, $idempotencyKey, $actor): CapitalContribution {
            $existing = CapitalContribution::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing instanceof CapitalContribution) {
                return $existing;
            }

            if ($amount < 1 || trim($purpose) === '') {
                throw ValidationException::withMessages([
                    'contribution_amount' => 'Indiquez un montant en francs et la raison de cet apport.',
                ]);
            }

            $contribution = new CapitalContribution([
                'contributor_id' => $actor->getKey(),
                'contribution_amount' => Money::from($amount)->value(),
                'declared_on' => now('Africa/Niamey')->toDateString(),
                'state' => CapitalContributionState::EnAttente->value,
                'purpose' => trim($purpose),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $contribution,
                operation: fn (): bool => $contribution->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'capital_contribution_recorded',
                newValues: $contribution->getAttributes(),
                reason: $contribution->purpose,
            );

            return $contribution;
        });
    }

    public function approve(CapitalContribution $contribution, User $actor): CapitalContribution
    {
        $this->assertDirector($actor, 'Seul un directeur peut approuver un apport d’argent personnel.');

        return DB::transaction(function () use ($contribution, $actor): CapitalContribution {
            $locked = $this->lockedPending($contribution, $actor);
            $approvedAt = CarbonImmutable::now('UTC');
            $oldValues = $locked->getRawOriginal();
            $locked->forceFill([
                'state' => CapitalContributionState::Approuve->value,
                'approved_by' => $actor->getKey(),
                'approved_at' => $approvedAt,
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: function () use ($locked, $actor, $approvedAt): bool {
                    $locked->saveOrFail();
                    $this->contributionShares->issueForCapitalContribution($locked, $actor, $approvedAt);

                    return true;
                },
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'capital_contribution_approved',
                oldValues: $oldValues,
                newValues: $locked->getAttributes(),
                reason: $locked->purpose,
            );

            return $locked;
        });
    }

    public function refuse(CapitalContribution $contribution, string $reason, User $actor): CapitalContribution
    {
        $this->assertDirector($actor, 'Seul un directeur peut refuser un apport d’argent personnel.');

        return DB::transaction(function () use ($contribution, $reason, $actor): CapitalContribution {
            $locked = $this->lockedPending($contribution, $actor);

            if (trim($reason) === '') {
                throw ValidationException::withMessages([
                    'refusal_reason' => 'Expliquez pourquoi cet apport est refusé.',
                ]);
            }

            $oldValues = $locked->getRawOriginal();
            $locked->forceFill([
                'state' => CapitalContributionState::Refuse->value,
                'refused_by' => $actor->getKey(),
                'refused_at' => CarbonImmutable::now('UTC'),
                'refusal_reason' => trim($reason),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'capital_contribution_refused',
                oldValues: $oldValues,
                newValues: $locked->getAttributes(),
                reason: trim($reason),
            );

            return $locked;
        });
    }

    /**
     * Apports visibles sur l'écran du registre, du plus récent au plus ancien.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingAndSettled(User $viewer): array
    {
        return CapitalContribution::query()
            ->with(['contributor.person', 'approver.person', 'refuser.person'])
            ->orderByDesc('declared_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CapitalContribution $contribution): array => [
                'id' => (int) $contribution->getKey(),
                'contributor_name' => $contribution->contributor->person->full_name,
                'amount_label' => Money::from($contribution->contribution_amount)->format(),
                'declared_on' => $contribution->declared_on->toDateString(),
                'state' => $contribution->state->value,
                'state_label' => $contribution->state->label(),
                'purpose' => $contribution->purpose,
                'refusal_reason' => $contribution->refusal_reason,
                'decided_by' => $contribution->approver?->person->full_name ?? $contribution->refuser?->person->full_name,
                // Un directeur n'approuve jamais son propre apport : la décision revient à l'autre.
                'can_decide' => $contribution->isPending() && (int) $contribution->contributor_id !== (int) $viewer->getKey(),
            ])
            ->all();
    }

    private function lockedPending(CapitalContribution $contribution, User $actor): CapitalContribution
    {
        $locked = CapitalContribution::query()->whereKey($contribution->getKey())->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw ValidationException::withMessages([
                'capital_contribution' => 'Cet apport a déjà été tranché ; un apport approuvé est définitif.',
            ]);
        }

        if ((int) $locked->contributor_id === (int) $actor->getKey()) {
            throw ValidationException::withMessages([
                'capital_contribution' => 'Un directeur ne peut pas trancher son propre apport : l’accord du second est requis.',
            ]);
        }

        return $locked;
    }

    private function assertDirector(User $actor, string $message): void
    {
        if (! $actor->hasRole('direction')) {
            throw new AuthorizationException($message);
        }
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
