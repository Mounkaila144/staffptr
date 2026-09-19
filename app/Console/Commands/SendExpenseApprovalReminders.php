<?php

namespace App\Console\Commands;

use App\Services\Finance\ExpenseApprovalReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ptr:send-expense-approval-reminders')]
#[Description('Envoie les rappels J+1/J+2 aux approbateurs de dépenses manquants')]
class SendExpenseApprovalReminders extends Command
{
    public function __construct(private readonly ExpenseApprovalReminderService $reminders)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->reminders->dispatchDue(CarbonImmutable::now('UTC'));
        $this->components->info("{$count} rappel(s) d'approbation créé(s).");

        return self::SUCCESS;
    }
}
