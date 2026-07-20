<?php

namespace App\Console\Commands;

use App\Services\Platform\Invariants\AuditDeletePrivilegeInvariant;
use App\Services\Platform\Invariants\AuditTriggersInvariant;
use App\Services\Platform\Invariants\EnvironmentInvariant;
use App\Services\Platform\Invariants\InvariantCheck;
use App\Services\Platform\Invariants\SuperAdminPermissionInvariant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ptr:check-invariants')]
#[Description("Vérifie les invariants de sécurité de l'installation")]
class CheckInvariants extends Command
{
    public function __construct(
        private readonly EnvironmentInvariant $environmentInvariant,
        private readonly SuperAdminPermissionInvariant $superAdminPermissionInvariant,
        private readonly AuditTriggersInvariant $auditTriggersInvariant,
        private readonly AuditDeletePrivilegeInvariant $auditDeletePrivilegeInvariant,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failures = 0;

        foreach ($this->checks() as $check) {
            $result = $check->check();

            if ($result->passed) {
                $this->components->info("{$result->name} — constaté : {$result->observed}");

                continue;
            }

            $failures++;
            $this->components->error(
                "Écart — {$result->name} — constaté : {$result->observed} — attendu : {$result->expected}",
            );
        }

        if ($failures > 0) {
            $this->components->error("{$failures} invariant(s) en écart.");

            return self::FAILURE;
        }

        $this->components->info('Les quatre invariants sont conformes.');

        return self::SUCCESS;
    }

    /** @return list<InvariantCheck> */
    private function checks(): array
    {
        return [
            $this->environmentInvariant,
            $this->superAdminPermissionInvariant,
            $this->auditTriggersInvariant,
            $this->auditDeletePrivilegeInvariant,
        ];
    }
}
