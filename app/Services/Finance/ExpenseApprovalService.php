<?php

namespace App\Services\Finance;

use App\Enums\AlertLevel;
use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Identity\User;
use App\Services\Identity\ExpenseApprovalReadiness;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseApprovalService
{
    public const REQUESTER_MESSAGE = 'Vous êtes le demandeur de cette dépense. Elle doit être approuvée par les deux autres comptes de direction.';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ExpenseApprovalReadiness $readiness,
        private readonly AlertLevelExpenseNotice $alertLevelNotice,
    ) {}

    public function approve(Expense $expense, User $approver): Expense
    {
        return $this->decide($expense, $approver, ExpenseApproval::DECISION_APPROVE, null);
    }

    public function refuse(Expense $expense, User $approver, string $reason): Expense
    {
        return $this->decide($expense, $approver, ExpenseApproval::DECISION_REJECT, $reason);
    }

    private function decide(Expense $expense, User $approver, string $decision, ?string $reason): Expense
    {
        return DB::connection($expense->getConnectionName())->transaction(function () use (
            $expense,
            $approver,
            $decision,
            $reason,
        ): Expense {
            $lockedExpense = Expense::query()
                ->whereKey($expense->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanApprove($approver);
            $this->assertNotRequester($lockedExpense, $approver);
            $this->assertNotShareBeneficiary($lockedExpense, $approver);
            $this->assertNoExistingDecision($lockedExpense, $approver);
            $this->assertRequested($lockedExpense);
            $this->assertApprovalAccountsReady();

            $normalizedReason = $decision === ExpenseApproval::DECISION_REJECT
                ? $this->requiredReason($reason)
                : null;
            $decidedAt = CarbonImmutable::now('UTC');
            $approvalCount = $lockedExpense->approvals()
                ->where('decision', ExpenseApproval::DECISION_APPROVE)
                ->distinct('approver_id')
                ->count('approver_id');
            $nextState = match (true) {
                $decision === ExpenseApproval::DECISION_REJECT => ExpenseState::Refusee,
                $approvalCount + 1 >= 2 => ExpenseState::Approuvee,
                default => ExpenseState::Demandee,
            };
            $approval = new ExpenseApproval;
            $approval->setConnection($lockedExpense->getConnectionName());
            $approval->fill([
                'expense_id' => $lockedExpense->getKey(),
                'approver_id' => $approver->getKey(),
                'decision' => $decision,
                'comment' => $normalizedReason,
                'decided_at' => $decidedAt,
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $lockedExpense,
                operation: function () use ($approval, $lockedExpense, $nextState): bool {
                    $approval->saveOrFail();

                    if ($lockedExpense->state !== $nextState) {
                        $lockedExpense->forceFill(['state' => $nextState->value])->saveOrFail();
                    }

                    return true;
                },
                actorId: (int) $approver->getKey(),
                actorLabel: $this->actorLabel($approver),
                action: $decision === ExpenseApproval::DECISION_APPROVE
                    ? 'expense_approved'
                    : 'expense_refused',
                oldValues: ['state' => $lockedExpense->state->value],
                newValues: [
                    'state' => $nextState->value,
                    'approval_decision' => $decision,
                    'approver_id' => (int) $approver->getKey(),
                    'decided_at' => $decidedAt->toISOString(),
                    // AC 13 : quand l'effet du niveau rouge s'est manifesté — un avertissement
                    // affiché sur une dépense non essentielle — l'audit nomme le niveau et
                    // l'objet. L'approbation, elle, n'a jamais été empêchée (AC 9).
                    ...($decision === ExpenseApproval::DECISION_APPROVE
                        && $this->alertLevelNotice->applies($lockedExpense)
                            ? ['alert_level' => AlertLevel::Rouge->value, 'alert_warning_shown' => true]
                            : []),
                ],
                reason: $normalizedReason,
            );

            return $lockedExpense->refresh()->load([
                'requester.person',
                'category',
                'approvals.approver.person',
            ]);
        });
    }

    private function assertCanApprove(User $approver): void
    {
        if (! $approver->can('depense.approuver')) {
            throw new AuthorizationException("Vous n'avez pas l'autorisation d'approuver une dépense.");
        }
    }

    private function assertNotRequester(Expense $expense, User $approver): void
    {
        if ((int) $expense->requester_id === (int) $approver->getKey()) {
            throw ValidationException::withMessages(['approval' => self::REQUESTER_MESSAGE]);
        }
    }

    private function assertNotShareBeneficiary(Expense $expense, User $approver): void
    {
        if ($expense->beneficiary_user_id !== null && (int) $expense->beneficiary_user_id === (int) $approver->getKey()) {
            throw ValidationException::withMessages(['approval' => 'Un bénéficiaire ne peut pas approuver sa propre part.']);
        }
    }

    private function assertNoExistingDecision(Expense $expense, User $approver): void
    {
        if ($expense->approvals()->where('approver_id', $approver->getKey())->exists()) {
            throw ValidationException::withMessages([
                'approval' => 'Vous avez déjà pris une décision pour cette dépense.',
            ]);
        }
    }

    private function assertRequested(Expense $expense): void
    {
        if ($expense->state !== ExpenseState::Demandee) {
            throw ValidationException::withMessages([
                'approval' => 'Cette dépense a déjà reçu une décision définitive.',
            ]);
        }
    }

    private function assertApprovalAccountsReady(): void
    {
        if (! $this->readiness->isApprovalAvailable()) {
            throw ValidationException::withMessages([
                'approval' => ExpenseApprovalReadiness::UNAVAILABLE_MESSAGE,
            ]);
        }
    }

    private function requiredReason(?string $reason): string
    {
        $normalizedReason = trim((string) $reason);

        if ($normalizedReason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Le motif du refus est obligatoire.',
            ]);
        }

        if (mb_strlen($normalizedReason) > 255) {
            throw ValidationException::withMessages([
                'reason' => 'Le motif du refus ne peut pas dépasser 255 caractères.',
            ]);
        }

        return $normalizedReason;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
