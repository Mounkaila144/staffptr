<?php

namespace App\Console\Commands;

use App\Services\Platform\Invariants\ApprovedExpenseHasTwoApprovalsInvariant;
use App\Services\Platform\Invariants\AuditDeletePrivilegeInvariant;
use App\Services\Platform\Invariants\AuditTriggersInvariant;
use App\Services\Platform\Invariants\BackupFreshnessInvariant;
use App\Services\Platform\Invariants\EnvironmentInvariant;
use App\Services\Platform\Invariants\ExpenseApproverCountInvariant;
use App\Services\Platform\Invariants\FinanceIntegrityInvariant;
use App\Services\Platform\Invariants\InvariantCheck;
use App\Services\Platform\Invariants\PaidExpenseIntegrityInvariant;
use App\Services\Platform\Invariants\QueuedNotificationFailureInvariant;
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
        private readonly ExpenseApproverCountInvariant $expenseApproverCountInvariant,
        private readonly ApprovedExpenseHasTwoApprovalsInvariant $approvedExpenseHasTwoApprovalsInvariant,
        private readonly PaidExpenseIntegrityInvariant $paidExpenseIntegrityInvariant,
        private readonly QueuedNotificationFailureInvariant $queuedNotificationFailureInvariant,
        private readonly FinanceIntegrityInvariant $financeIntegrityInvariant,
        private readonly BackupFreshnessInvariant $backupFreshnessInvariant,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failures = 0;
        $pending = 0;
        $passed = 0;

        foreach ($this->checks() as $check) {
            $result = $check->check();

            if ($result->passed) {
                $passed++;
                $this->components->info("{$result->name} — constaté : {$result->observed}");

                continue;
            }

            // Un contrôle en attente d'une dépendance non livrée reste visible sans rendre la
            // commande rouge en permanence — une alerte permanente cesse vite d'être lue.
            if ($result->pending) {
                $pending++;
                $this->components->warn(
                    "En attente — {$result->name} — constaté : {$result->observed} — attendu : {$result->expected}",
                );

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

        $this->components->info("{$passed} invariant(s) conforme(s).");

        if ($pending > 0) {
            $this->components->warn(
                "{$pending} invariant(s) en attente d'une dépendance non livrée : la porte MVP ne peut pas être prononcée tant qu'ils restent ouverts.",
            );
        }

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
            $this->expenseApproverCountInvariant,
            $this->approvedExpenseHasTwoApprovalsInvariant,
            $this->paidExpenseIntegrityInvariant,
            $this->queuedNotificationFailureInvariant,
            $this->financeIntegrityInvariant,
            // Story 10.1 AC 20 : la fraîcheur de sauvegarde complète l'ensemble cumulé. Son
            // contrat appartient à 11.1 ; tant qu'il manque, le contrôle se déclare en attente.
            $this->backupFreshnessInvariant,
        ];
    }
}
