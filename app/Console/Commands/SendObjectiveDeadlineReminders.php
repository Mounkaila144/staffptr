<?php

namespace App\Console\Commands;

use App\Services\Work\ObjectiveDeadlineReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * « Objectif proche de l'échéance » (FR31).
 *
 * Idempotente : l'identité de chaque notification est `objectif + destinataire + date civile`,
 * si bien qu'un rejeu le même jour n'émet rien (AC 35, AC 44).
 */
#[Signature('ptr:send-objective-deadline-reminders')]
#[Description("Notifie les objectifs dont l'échéance approche")]
class SendObjectiveDeadlineReminders extends Command
{
    public function __construct(private readonly ObjectiveDeadlineReminderService $reminders)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->reminders->dispatchDue(CarbonImmutable::now('UTC'));
        $this->components->info("{$count} notification(s) d'échéance d'objectif créée(s).");

        return self::SUCCESS;
    }
}
