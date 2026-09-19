<?php

namespace App\Console\Commands;

use App\Services\Accountability\DailyReportReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ptr:send-daily-report-reminders')]
#[Description('Émet les rappels et notifications de retard des rapports quotidiens attendus')]
class SendDailyReportReminders extends Command
{
    public function __construct(private readonly DailyReportReminderService $reminders)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->reminders->dispatchDue(CarbonImmutable::now('UTC'));
        $this->components->info("{$count} notification(s) de rapport créée(s).");

        return self::SUCCESS;
    }
}
