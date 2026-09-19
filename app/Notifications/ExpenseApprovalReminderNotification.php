<?php

namespace App\Notifications;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Identity\User;
use App\Services\Platform\WhatsAppChannel;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class ExpenseApprovalReminderNotification extends BaseNotification
{
    /** @param list<string> $channels */
    public function __construct(
        public readonly Expense $expense,
        public readonly int $dayOffset,
        public readonly int $recipientId,
        private readonly array $channels,
    ) {
        if (! in_array($dayOffset, [1, 2], true) || $channels === []) {
            throw new InvalidArgumentException("L'échéance et les canaux du rappel sont invalides.");
        }

        $this->id = self::stableId(
            (int) $expense->getKey(),
            $recipientId,
            $dayOffset,
        );
    }

    public static function forDatabase(Expense $expense, int $dayOffset, int $recipientId): self
    {
        return new self($expense, $dayOffset, $recipientId, ['database']);
    }

    public static function forWhatsApp(Expense $expense, int $dayOffset, int $recipientId): self
    {
        return new self($expense, $dayOffset, $recipientId, [WhatsAppChannel::class]);
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** @return array{message: string, link: string, expense_id: int, reminder_day: int} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link(),
            'expense_id' => (int) $this->expense->getKey(),
            'reminder_day' => $this->dayOffset,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link());
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User
            || (int) $notifiable->getKey() !== $this->recipientId
            || ! $notifiable->can('depense.approuver')) {
            return false;
        }

        return Expense::query()
            ->whereKey($this->expense->getKey())
            ->where('state', ExpenseState::Demandee->value)
            ->where('requester_id', '!=', $notifiable->getKey())
            ->whereDoesntHave('approvals', static function (Builder $query) use ($notifiable): void {
                $query->where('approver_id', $notifiable->getKey());
            })
            ->exists();
    }

    public static function stableId(int $expenseId, int $recipientId, int $dayOffset): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "ptr-staff:expense-approval-reminder:{$expenseId}:{$recipientId}:J+{$dayOffset}",
        )->toString();
    }

    private function link(): string
    {
        $link = route('expenses.approvals.show', $this->expense, false);

        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            throw new InvalidArgumentException("Le lien d'un rappel doit être interne à l'application.");
        }

        return $link;
    }

    private function message(): string
    {
        $this->expense->loadMissing(['requester.person', 'category']);
        $amount = Money::from($this->expense->requested_amount)->format();

        return "Rappel J+{$this->dayOffset} — la dépense de {$amount} demandée par {$this->expense->requester->person->full_name} attend votre décision.";
    }
}
