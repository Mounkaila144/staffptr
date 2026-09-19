<?php

namespace App\Console\Commands;

use App\Enums\AlertLevel;
use App\Services\Finance\AlertLevelService;
use App\Services\Finance\CorrectionPlanReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recalcul planifié du niveau d'alerte, et relance du plan correctif si le mois est orange
 * (AC 5, AC 7, AC 11).
 *
 * La commande est **idempotente** : rejouée sur les mêmes données elle réécrit les mêmes valeurs,
 * ne repousse pas la date d'observation du niveau et ne recrée aucune notification déjà émise le
 * même jour. Elle ne recalcule jamais un mois clos : le niveau figé à la clôture fait foi.
 */
#[Signature('ptr:recalculate-alert-level {--month= : Mois à recalculer au format AAAA-MM, par défaut le mois courant}')]
#[Description("Recalcule le niveau d'alerte financière du mois et relance le plan correctif en orange")]
class RecalculateAlertLevel extends Command
{
    public function __construct(
        private readonly AlertLevelService $alertLevelService,
        private readonly CorrectionPlanReminderService $reminders,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $month = $this->option('month');
        $assessment = $this->alertLevelService->recalculate(
            is_string($month) && $month !== '' ? $month.'-01' : null,
        );

        $this->components->info(sprintf(
            'Niveau %s pour %s (assiette %d F CFA, encaissements %d F CFA)%s.',
            $assessment->level->label(),
            $assessment->monthLabel(),
            $assessment->baseline,
            $assessment->collections,
            $assessment->frozen ? ' — niveau figé à la clôture, non recalculé' : '',
        ));

        if ($assessment->level === AlertLevel::Orange) {
            $sent = $this->reminders->dispatchDue();
            $this->components->info("{$sent} relance(s) de plan correctif créée(s).");
        }

        return self::SUCCESS;
    }
}
