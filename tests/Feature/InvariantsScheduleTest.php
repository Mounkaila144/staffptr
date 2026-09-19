<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Identity\User;
use App\Services\Platform\Invariants\ApprovedExpenseHasTwoApprovalsInvariant;
use App\Services\Platform\Invariants\BackupFreshnessInvariant;
use Carbon\CarbonImmutable;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Story 10.1, Task 5 — version finale de `ptr:check-invariants` (AC 19 à 21, AC 24, AC 38).
 *
 * La commande n'est pas recréée : elle existe depuis la story 2.3 et a été enrichie en 4.5. Cette
 * story y ajoute le contrôle de fraîcheur de sauvegarde et l'exécution quotidienne planifiée.
 */
class InvariantsScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ExpenseCategorySeeder::class);
    }

    /** AC 19 — la commande de 2.3 est enrichie, pas remplacée : elle garde sa signature. */
    public function test_ac_19_the_command_created_in_story_2_3_is_extended_not_recreated(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/CheckInvariants.php'));

        $this->assertStringContainsString("Signature('ptr:check-invariants')", $source);
        // L'ensemble cumulé de l'AC 20, contrôle par contrôle.
        foreach ([
            'EnvironmentInvariant',                    // APP_DEBUG / APP_ENV
            'ExpenseApproverCountInvariant',           // exactement 2 approbateurs
            'SuperAdminPermissionInvariant',           // super_admin sans permission métier
            'AuditTriggersInvariant',                  // déclencheurs d'immuabilité
            'AuditDeletePrivilegeInvariant',           // pas de DELETE sur audit_logs
            'ApprovedExpenseHasTwoApprovalsInvariant', // dérive dans les données (AC 21)
            'BackupFreshnessInvariant',                // sauvegarde < 26 h (AC 20)
        ] as $invariant) {
            $this->assertStringContainsString($invariant, $source, "Invariant manquant : {$invariant}.");
        }
    }

    /**
     * AC 21 — **le point le plus important** : la commande détecte, *dans les données*, une
     * dépense `payee` sans deux approbations distinctes. C'est ce qui révèle une manipulation en
     * base ou une régression déjà passée en production, qu'aucun garde applicatif ne verrait.
     */
    public function test_ac_21_a_forged_paid_expense_without_two_distinct_approvals_is_detected(): void
    {
        $clean = app(ApprovedExpenseHasTwoApprovalsInvariant::class)->check();
        $this->assertTrue($clean->passed);

        // Contournement délibéré des services : on écrit l'état comme le ferait une manipulation.
        $approver = User::factory()->active()->withRole('direction')->create();
        $expense = Expense::factory()->create(['state' => ExpenseState::Demandee->value]);
        ExpenseApproval::query()->create([
            'expense_id' => $expense->getKey(),
            'approver_id' => $approver->getKey(),
            'decision' => ExpenseApproval::DECISION_APPROVE,
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);
        $expense->forceFill(['state' => ExpenseState::Payee->value])->saveQuietly();

        $drifted = app(ApprovedExpenseHasTwoApprovalsInvariant::class)->check();

        $this->assertFalse($drifted->passed, 'Une dépense payée avec une seule approbation doit être détectée.');
        $this->assertStringContainsString((string) $expense->getKey(), $drifted->observed);
    }

    /**
     * AC 21 — deux approbations du **même** compte ne peuvent pas simuler deux approbateurs
     * distincts.
     *
     * La garantie s'avère plus forte que l'invariant : la base porte un index unique
     * `(expense_id, approver_id)` qui rend le doublon impossible à écrire, même par manipulation
     * directe. L'invariant reste néanmoins utile — il couvre le cas d'une dépense payée sans
     * *aucune* seconde approbation, que l'index n'empêche pas.
     */
    public function test_ac_21_two_approvals_from_the_same_account_cannot_even_be_written(): void
    {
        $approver = User::factory()->active()->withRole('direction')->create();
        $expense = Expense::factory()->create(['state' => ExpenseState::Demandee->value]);

        $write = fn (): mixed => ExpenseApproval::query()->create([
            'expense_id' => $expense->getKey(),
            'approver_id' => $approver->getKey(),
            'decision' => ExpenseApproval::DECISION_APPROVE,
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);

        $write();
        $refused = false;

        try {
            $write();
        } catch (QueryException) {
            $refused = true;
        }

        $this->assertTrue($refused, 'La base doit refuser deux décisions du même approbateur.');
        $this->assertSame(1, ExpenseApproval::query()->where('expense_id', $expense->getKey())->count());
    }

    /**
     * AC 20 — le contrôle de sauvegarde **ne se déclare jamais satisfait sans sauvegarde réelle**.
     *
     * La story 10.1 l'avait posé en état `pending`, faute du contrat de sauvegarde. **La story
     * 11.1 a livré ce contrat** : le disque `backups` existe, le contrôle est donc effectif et
     * signale un écart franc quand aucune archive n'est présente.
     *
     * L'état `pending` n'a pas disparu pour autant — il reste le comportement sur un environnement
     * dépourvu de disque de sauvegarde, ce que vérifie le test suivant.
     */
    public function test_ac_20_the_backup_check_is_now_effective_and_reports_a_real_gap(): void
    {
        Storage::fake('backups');

        $result = app(BackupFreshnessInvariant::class)->check();

        $this->assertFalse($result->passed, 'Le contrôle ne doit pas se déclarer satisfait sans sauvegarde réelle.');
        $this->assertFalse($result->pending, 'Le contrat étant livré, le contrôle n’est plus en attente.');
        $this->assertStringContainsString('aucune sauvegarde', $result->observed);
        $this->assertStringContainsString('26 heures', $result->expected);
    }

    /** AC 20 — sans disque de sauvegarde configuré, le contrôle reste en attente plutôt qu'en écart. */
    public function test_ac_20_the_check_stays_pending_where_no_backup_disk_is_configured(): void
    {
        config(['filesystems.disks.backups' => null]);

        $result = app(BackupFreshnessInvariant::class)->check();

        $this->assertTrue($result->pending);
        $this->assertStringContainsString('non configuré', $result->observed);
    }

    /** AC 19, AC 24 — l'exécution est quotidienne, en heure de Niamey, et sans chevauchement. */
    public function test_ac_19_and_24_the_command_runs_daily_in_niamey_time(): void
    {
        $schedule = (string) file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString('ptr:check-invariants', $schedule);
        $this->assertMatchesRegularExpression(
            "/ptr:check-invariants'\)\s*->dailyAt\('\d{2}:\d{2}'\)\s*->timezone\('Africa\/Niamey'\)/s",
            $schedule,
        );
    }

    /**
     * AC 24, AC 38 — un écart d'invariant sort en **code d'erreur**. C'est ce signal que la
     * supervision transforme en alerte ; une commande qui sortirait toujours à zéro ne pourrait
     * alerter personne.
     */
    public function test_ac_24_a_failing_invariant_exits_with_an_error_code(): void
    {
        $approver = User::factory()->active()->withRole('direction')->create();
        $expense = Expense::factory()->create(['state' => ExpenseState::Demandee->value]);
        ExpenseApproval::query()->create([
            'expense_id' => $expense->getKey(),
            'approver_id' => $approver->getKey(),
            'decision' => ExpenseApproval::DECISION_APPROVE,
            'decided_at' => CarbonImmutable::now('UTC'),
        ]);
        $expense->forceFill(['state' => ExpenseState::Payee->value])->saveQuietly();

        $this->artisan('ptr:check-invariants')->assertFailed();
    }

    /**
     * AC 38 — un contrôle en attente n'est jamais confondu avec un contrôle réussi ni avec un
     * écart. Les trois états restent distincts dans la sortie de la commande.
     */
    public function test_ac_38_a_pending_check_is_reported_separately_from_a_passing_one(): void
    {
        // Sans disque de sauvegarde, le contrôle correspondant se déclare en attente.
        config(['filesystems.disks.backups' => null]);

        $this->artisan('ptr:check-invariants')
            ->expectsOutputToContain('En attente')
            ->run();

        $source = (string) file_get_contents(base_path('app/Console/Commands/CheckInvariants.php'));
        $this->assertStringContainsString('$result->pending', $source);
        $this->assertStringContainsString("en attente d'une dépendance non livrée", $source);
    }
}
