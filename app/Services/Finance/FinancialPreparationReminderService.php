<?php

namespace App\Services\Finance;

use App\Enums\ReconciliationState;
use App\Enums\UserState;
use App\Models\Finance\Reconciliation;
use App\Models\Identity\User;
use App\Notifications\FinancialPreparationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Volet **rapprochement bancaire** de l'événement FR31 « rapprochement ou rapport financier à
 * préparer » (AC 32, AC 35).
 *
 * Le volet rapport financier appartient à {@see FinancialReportReminderService}, livré par
 * l'Epic 8 : il n'est pas réécrit ici, il est simplement ordonnancé par la même commande. Un seul
 * événement de FR31, deux services propriétaires de leur échéance, et aucune notification en
 * double.
 *
 * L'échéance porte sur le **mois précédent** : c'est lui qu'on rapproche. Le rappel s'éteint dès
 * que la préparation existe, et son identité ne comprend pas la date du jour : une tâche
 * quotidienne ne peut donc pas produire trente relances pour une même échéance mensuelle.
 */
final readonly class FinancialPreparationReminderService
{
    private const TIMEZONE = 'Africa/Niamey';

    /** Jour du mois à partir duquel le mois précédent est réclamé. */
    public const DUE_DAY_OF_MONTH = 5;

    public function dispatchDue(?CarbonImmutable $now = null): int
    {
        $today = ($now ?? CarbonImmutable::now('UTC'))->setTimezone(self::TIMEZONE)->startOfDay();

        if ((int) $today->format('j') < self::DUE_DAY_OF_MONTH) {
            return 0;
        }

        $month = $today->startOfMonth()->subMonth();

        if (! $this->reconciliationIsPending($month)) {
            return 0;
        }

        $sent = 0;

        foreach ($this->recipients() as $recipient) {
            if ($this->dispatchOne($recipient, $month->toDateString(), $today->toDateString())) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Le rapprochement du mois reste-t-il à préparer ? Une préparation déjà engagée, même encore
     * en brouillon, éteint le rappel : elle prouve que le geste a été fait.
     */
    public function reconciliationIsPending(CarbonImmutable $month): bool
    {
        return ! Reconciliation::query()
            ->whereIn('state', [ReconciliationState::Draft->value, ReconciliationState::Validated->value])
            ->whereBetween('period_end', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->exists();
    }

    /** @return Collection<int, User> */
    private function recipients(): Collection
    {
        return User::permission('rapprochement.preparer')
            ->where('state', UserState::Actif)
            ->orderBy('id')
            ->get();
    }

    private function dispatchOne(User $recipient, string $month, string $reminderDate): bool
    {
        $kind = FinancialPreparationNotification::KIND_RECONCILIATION;
        $notificationId = FinancialPreparationNotification::stableId(
            (int) $recipient->getKey(),
            $kind,
            $month,
        );

        $claimed = DB::connection($recipient->getConnectionName())->transaction(
            function () use ($recipient, $kind, $month, $reminderDate, $notificationId): bool {
                if (DatabaseNotification::query()->whereKey($notificationId)->exists()) {
                    return false;
                }

                Notification::sendNow(
                    $recipient,
                    FinancialPreparationNotification::forDatabase(
                        (int) $recipient->getKey(),
                        $kind,
                        $month,
                        $reminderDate,
                    ),
                    ['database'],
                );

                return true;
            }
        );

        if (! $claimed) {
            return false;
        }

        $recipient->notify(FinancialPreparationNotification::forWhatsApp(
            (int) $recipient->getKey(),
            $kind,
            $month,
            $reminderDate,
        ));

        return true;
    }
}
