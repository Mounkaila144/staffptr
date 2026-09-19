<?php

namespace App\Console\Commands;

use App\Services\Accountability\SupportRequestGroupingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ptr:deliver-support-request-batches')]
#[Description('Émet, au créneau de suivi de chaque tuteur, la notification groupée des demandes non urgentes')]
class DeliverSupportRequestBatches extends Command
{
    public function __construct(private readonly SupportRequestGroupingService $grouping)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->grouping->deliverDue(CarbonImmutable::now('UTC'));
        $this->components->info("{$count} notification(s) groupée(s) émise(s).");

        return self::SUCCESS;
    }
}
