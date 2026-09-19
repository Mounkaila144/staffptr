<?php

namespace App\Console\Commands;

use App\Services\Identity\ContractEndingReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * « Fin de contrat ou de stage proche » (FR31, AC 32, AC 33).
 *
 * C'est le consommateur qui manquait au service exposé par la story 3.2 : la détection existait
 * depuis ce jalon, sans destinataire.
 */
#[Signature('ptr:send-contract-ending-reminders')]
#[Description('Notifie les fins de contrat et de stage proches détectées par le service de la story 3.2')]
class SendContractEndingReminders extends Command
{
    public function __construct(private readonly ContractEndingReminderService $reminders)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->reminders->dispatchDue(CarbonImmutable::now('UTC'));
        $this->components->info("{$count} notification(s) de fin de contrat ou de stage créée(s).");

        return self::SUCCESS;
    }
}
