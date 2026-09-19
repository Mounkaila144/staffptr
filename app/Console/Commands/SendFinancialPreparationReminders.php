<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialPreparationReminderService;
use App\Services\Finance\FinancialReportReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * « Rapprochement ou rapport financier à préparer » (FR31).
 *
 * L'événement de FR31 recouvre deux échéances portées par deux services propriétaires : le
 * rapprochement bancaire ({@see FinancialPreparationReminderService}) et le rapport financier
 * mensuel ({@see FinancialReportReminderService}, livré par l'Epic 8 mais qui n'était jusqu'ici
 * jamais déclenché faute de tâche planifiée). Cette commande les ordonnance ensemble.
 *
 * L'identité des notifications ne comprend pas la date du jour : une échéance mensuelle ne produit
 * qu'un rappel par destinataire, même si la tâche tourne quotidiennement (AC 35).
 */
#[Signature('ptr:send-financial-preparation-reminders')]
#[Description('Notifie le rapprochement bancaire et le rapport financier du mois précédent à préparer')]
class SendFinancialPreparationReminders extends Command
{
    public function __construct(
        private readonly FinancialPreparationReminderService $reconciliations,
        private readonly FinancialReportReminderService $monthlyReports,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');
        $count = $this->reconciliations->dispatchDue($now)
            + $this->monthlyReports->dispatchDue($now->setTimezone('Africa/Niamey'));

        $this->components->info("{$count} notification(s) de préparation financière créée(s).");

        return self::SUCCESS;
    }
}
