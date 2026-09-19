<?php

namespace App\Notifications;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Identity\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ExpenseRequestedNotification extends BaseNotification
{
    public function __construct(
        public readonly Expense $expense,
    ) {
        $link = route('expenses.approvals.show', $this->expense, false);

        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            throw new InvalidArgumentException("Le lien d'une notification doit être interne à l'application.");
        }

        $this->link = $link;
    }

    public readonly string $link;

    /** @return array{message: string, link: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message(),
            'link' => $this->link,
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "PTR Staff — {$this->message()}\n".url($this->link);
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $notifiable instanceof User || ! $notifiable->can('depense.approuver')) {
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

    private function message(): string
    {
        $amount = Money::from($this->expense->requested_amount)->format();
        $category = $this->expense->category->name;
        $requester = $this->expense->requester->person->full_name;

        return "{$requester} a demandé {$amount} pour « {$this->expense->reason} » ({$category}).";
    }
}
