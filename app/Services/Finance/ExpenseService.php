<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Notifications\ExpenseRequestedNotification;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AttachmentService $attachmentService,
    ) {}

    public function create(
        User $requester,
        ExpenseCategory $category,
        string $reason,
        int $requestedAmount,
        string $beneficiary,
        string $expectedResult,
        ?string $projectOrContractNote,
        ?Attachment $attachment,
        User $actor,
    ): Expense {
        // AC 3: creation is open to all authenticated users, not permission-based
        if ((int) $requester->getKey() !== (int) $actor->getKey()) {
            throw new AuthorizationException('Une demande de dépense ne peut être créée que pour soi-même.');
        }

        $this->assertPositiveAmount($requestedAmount);
        $this->assertCategoryActive($category);

        $expense = new Expense;

        $createdExpense = DB::connection($expense->getConnectionName())->transaction(function () use (
            $expense,
            $requester,
            $category,
            $reason,
            $requestedAmount,
            $beneficiary,
            $expectedResult,
            $projectOrContractNote,
            $attachment,
            $actor,
        ): Expense {
            $lockedAttachment = null;

            if ($attachment instanceof Attachment) {
                $lockedAttachment = Attachment::query()->whereKey($attachment->getKey())->lockForUpdate()->firstOrFail();
                $this->assertStagedForActor($lockedAttachment, $actor);
            }

            $expense->fill([
                'requester_id' => $requester->getKey(),
                'category_id' => $category->getKey(),
                'reason' => $reason,
                'requested_amount' => $requestedAmount,
                'beneficiary' => $beneficiary,
                'expected_result' => $expectedResult,
                'project_or_contract_note' => $projectOrContractNote,
            ]);
            $expense->forceFill(['state' => ExpenseState::Demandee->value]);

            $this->auditLogger->runExplicitly(
                auditable: $expense,
                operation: fn (): bool => $expense->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'expense_requested',
                newValues: [
                    ...$expense->getAttributes(),
                    'attachment_ulid' => $lockedAttachment?->ulid,
                ],
            );

            if ($lockedAttachment instanceof Attachment) {
                $this->attachmentService->attachExisting($lockedAttachment, $expense, $actor);
            }

            return $expense->load(['requester.person', 'category', 'attachment']);
        });

        $approvers = User::permission('depense.approuver')
            ->whereKeyNot($requester->getKey())
            ->get();
        Notification::send($approvers, new ExpenseRequestedNotification($createdExpense));

        return $createdExpense;
    }

    public function update(
        Expense $expense,
        ?ExpenseCategory $category,
        ?string $reason,
        ?int $requestedAmount,
        ?string $beneficiary,
        ?string $expectedResult,
        ?string $projectOrContractNote,
        ?Attachment $attachment,
        User $actor,
    ): Expense {
        return DB::connection($expense->getConnectionName())->transaction(function () use (
            $expense,
            $category,
            $reason,
            $requestedAmount,
            $beneficiary,
            $expectedResult,
            $projectOrContractNote,
            $attachment,
            $actor,
        ): Expense {
            $lockedExpense = $this->locked($expense);

            if ((int) $lockedExpense->requester_id !== (int) $actor->getKey()) {
                throw new AuthorizationException('Seul le demandeur peut modifier cette demande.');
            }

            $this->assertPending($lockedExpense);

            if ($requestedAmount !== null) {
                $this->assertPositiveAmount($requestedAmount);
            }

            if ($category !== null) {
                $this->assertCategoryActive($category);
            }

            $lockedAttachment = null;

            if ($attachment instanceof Attachment) {
                $lockedAttachment = Attachment::query()->whereKey($attachment->getKey())->lockForUpdate()->firstOrFail();
                $this->assertStagedForActor($lockedAttachment, $actor);
            }

            $oldValues = Arr::only($lockedExpense->getRawOriginal(), [
                'category_id',
                'reason',
                'requested_amount',
                'beneficiary',
                'expected_result',
                'project_or_contract_note',
            ]);

            $lockedExpense->fill(array_filter([
                'category_id' => $category?->getKey(),
                'reason' => $reason,
                'requested_amount' => $requestedAmount,
                'beneficiary' => $beneficiary,
                'expected_result' => $expectedResult,
                'project_or_contract_note' => $projectOrContractNote,
            ], fn ($value) => $value !== null));

            $newValues = Arr::only($lockedExpense->getAttributes(), [
                'category_id',
                'reason',
                'requested_amount',
                'beneficiary',
                'expected_result',
                'project_or_contract_note',
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $lockedExpense,
                operation: fn (): bool => $lockedExpense->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'expense_updated',
                oldValues: $oldValues,
                newValues: $newValues,
            );

            if ($lockedAttachment instanceof Attachment) {
                // Detach old attachment if exists
                $lockedExpense->attachment()->delete();
                $this->attachmentService->attachExisting($lockedAttachment, $lockedExpense, $actor);
            }

            return $lockedExpense->refresh()->load(['requester.person', 'category', 'attachment']);
        });
    }

    public function cancel(Expense $expense, string $cancelReason, User $actor): Expense
    {
        $cancelReason = trim($cancelReason);

        if ($cancelReason === '') {
            throw ValidationException::withMessages([
                'cancel_reason' => 'Le motif d\'annulation est obligatoire.',
            ]);
        }

        return DB::connection($expense->getConnectionName())->transaction(function () use ($expense, $cancelReason, $actor): Expense {
            $lockedExpense = $this->locked($expense);

            if ((int) $lockedExpense->requester_id !== (int) $actor->getKey() && ! $actor->can('depense.consulter')) {
                throw new AuthorizationException('Seul le demandeur ou la direction peut annuler cette demande.');
            }

            $this->assertPending($lockedExpense);

            $oldValues = Arr::only($lockedExpense->getRawOriginal(), ['state', 'cancel_reason']);
            $lockedExpense->forceFill([
                'state' => ExpenseState::Annulee->value,
                'cancel_reason' => $cancelReason,
            ]);
            $newValues = Arr::only($lockedExpense->getAttributes(), ['state', 'cancel_reason']);

            $this->auditLogger->runExplicitly(
                auditable: $lockedExpense,
                operation: fn (): bool => $lockedExpense->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'expense_cancelled',
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $lockedExpense->refresh()->load(['requester.person', 'category', 'attachment']);
        });
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'requested_amount' => 'Le montant doit être un entier positif.',
            ]);
        }
    }

    private function assertCategoryActive(ExpenseCategory $category): void
    {
        if (! $category->is_active) {
            throw ValidationException::withMessages([
                'category_id' => 'Cette catégorie de dépense n\'est pas active.',
            ]);
        }
    }

    private function assertPending(Expense $expense): void
    {
        if ($expense->state !== ExpenseState::Demandee) {
            throw ValidationException::withMessages([
                'state' => 'Cette demande a déjà reçu une décision et ne peut plus être modifiée.',
            ]);
        }
    }

    private function locked(Expense $expense): Expense
    {
        return Expense::query()
            ->with(['requester.person', 'category'])
            ->whereKey($expense->getKey())
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

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
