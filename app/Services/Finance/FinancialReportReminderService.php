<?php

namespace App\Services\Finance;

use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;
use App\Notifications\FinancialReportPreparationReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Rappel « rapport financier à préparer » — l'un des onze événements de FR31.
 *
 * L'émission suit le patron du produit (architecture § 9.4) : l'écriture `database` fait foi et
 * est réalisée en premier, sous verrou de transaction ; WhatsApp n'est mis en file qu'ensuite, et
 * seulement si la transaction a effectivement créé la notification. Si la file est en panne, la
 * notification existe quand même dans l'application, et un rejeu ne peut pas la dupliquer
 * (story 9.1 AC 32, AC 35, AC 36).
 */
final class FinancialReportReminderService
{
    public function dispatchDue(CarbonImmutable $today): int
    {
        $month = $today->setTimezone('Africa/Niamey')->subMonthNoOverflow()->startOfMonth();

        if (MonthlyReport::query()->whereDate('month', $month->toDateString())->exists()) {
            return 0;
        }

        $phase = $today->day >= 5 ? 'retard' : 'approche';
        $count = 0;

        foreach (User::permission('rapport_financier.preparer')->where('state', 'actif')->get() as $recipient) {
            if ($this->dispatchOne($recipient, $month, $phase)) {
                $count++;
            }
        }

        return $count;
    }

    private function dispatchOne(User $recipient, CarbonImmutable $month, string $phase): bool
    {
        $recipientId = (int) $recipient->getKey();
        $databaseNotification = FinancialReportPreparationReminderNotification::forDatabase(
            $month,
            $phase,
            $recipientId,
        );

        $claimed = DB::connection($recipient->getConnectionName())->transaction(
            function () use ($recipient, $databaseNotification): bool {
                if (DatabaseNotification::query()->whereKey($databaseNotification->id)->exists()) {
                    return false;
                }

                Notification::sendNow($recipient, $databaseNotification, ['database']);

                return true;
            }
        );

        if (! $claimed) {
            return false;
        }

        // La ligne `database` vient d'être écrite : le travail en file ne porte que WhatsApp.
        $recipient->notify(FinancialReportPreparationReminderNotification::forWhatsApp(
            $month,
            $phase,
            $recipientId,
        ));

        return true;
    }
}
