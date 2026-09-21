<?php

use App\Enums\CapitalContributionState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des parts de contribution des directeurs (story 12.1).
 *
 * `contribution_shares` est un livre en ajout seul : une part émise ne se modifie ni ne se
 * supprime, et son annulation prend la forme d'une écriture inverse datée. `capital_contributions`
 * reste modifiable tant que l'apport attend le second directeur, puis se fige.
 *
 * Les deux tables reçoivent `GRANT UPDATE` au compte applicatif, conformément à
 * `docs/ops/database-users.md` : `SELECT ... FOR UPDATE` l'exige même là où l'écriture est
 * interdite. Les déclencheurs restent la garde réelle, comme pour `reserve_movements`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contributor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('contribution_amount');
            $table->date('declared_on');
            $table->string('state', 20)->default(CapitalContributionState::EnAttente->value)->index();
            $table->text('purpose');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at', 3)->nullable();
            $table->foreignId('refused_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('refused_at', 3)->nullable();
            $table->text('refusal_reason')->nullable();
            $table->ulid('idempotency_key')->unique();
            $table->timestamps(3);
            $table->index(['contributor_id', 'state']);
        });

        Schema::create('contribution_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('holder_id')->constrained('users')->restrictOnDelete();
            $table->string('origin', 20)->index();
            $table->string('entry_type', 20);
            $table->foreignId('share_entitlement_id')->nullable()->constrained('share_entitlements')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignId('capital_contribution_id')->nullable()->constrained('capital_contributions')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('contribution_shares')->restrictOnDelete();
            $table->unsignedBigInteger('base_amount');
            $table->unsignedSmallInteger('vintage_year');
            $table->unsignedInteger('coefficient_basis_points');
            $table->unsignedBigInteger('issued_shares');
            $table->date('occurred_on');
            $table->string('reason', 255);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps(3);
            // Idempotence : un droit `ptr_niger` n'émet qu'une part et ne s'annule qu'une fois.
            // Les colonnes nulles ne se heurtent pas entre elles, chaque origine garde la sienne.
            $table->unique(['share_entitlement_id', 'entry_type'], 'contribution_shares_entitlement_unique');
            $table->unique(['capital_contribution_id', 'entry_type'], 'contribution_shares_capital_unique');
            $table->index(['holder_id', 'occurred_on']);
        });

        $this->createDeleteTrigger('capital_contributions');
        $this->createDeleteTrigger('contribution_shares');
        $this->createImmutableUpdateTrigger('contribution_shares');
        $this->createSettledImmutableTrigger('capital_contributions');
        $this->grantApplicationUpdate();
    }

    public function down(): void
    {
        $this->revokeApplicationUpdate();

        foreach (['capital_contributions', 'contribution_shares'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS finance_no_delete_{$table}");
        }

        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_contribution_shares');
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_settled_capital_contributions');

        Schema::dropIfExists('contribution_shares');
        Schema::dropIfExists('capital_contributions');
    }

    private function createDeleteTrigger(string $table): void
    {
        $name = "finance_no_delete_{$table}";
        $message = "La suppression physique de {$table} est interdite.";

        if ($this->isMysqlFamily()) {
            DB::unprepared(
                "CREATE TRIGGER {$name} BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }

    private function createImmutableUpdateTrigger(string $table): void
    {
        $name = "finance_no_update_{$table}";
        $message = 'Une part de contribution émise est immuable.';

        if ($this->isMysqlFamily()) {
            DB::unprepared(
                "CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }

    private function createSettledImmutableTrigger(string $table): void
    {
        $name = "finance_no_update_settled_{$table}";
        $message = 'Un apport approuvé ou refusé est définitif.';
        $pending = CapitalContributionState::EnAttente->value;

        if ($this->isMysqlFamily()) {
            DB::unprepared(
                "CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF OLD.state <> '{$pending}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} WHEN OLD.state <> '{$pending}' BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }

    private function grantApplicationUpdate(): void
    {
        if (! $this->isMysqlFamily()) {
            return;
        }

        foreach (['capital_contributions', 'contribution_shares'] as $table) {
            DB::unprepared("GRANT UPDATE ON {$this->qualifiedTable($table)} TO {$this->applicationAccount()}");
        }
    }

    private function revokeApplicationUpdate(): void
    {
        if (! $this->isMysqlFamily()) {
            return;
        }

        foreach (['capital_contributions', 'contribution_shares'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::unprepared("REVOKE UPDATE ON {$this->qualifiedTable($table)} FROM {$this->applicationAccount()}");
        }
    }

    private function isMysqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function qualifiedTable(string $table): string
    {
        $database = DB::connection()->getDatabaseName();

        return '`'.str_replace('`', '``', $database).'`.`'.str_replace('`', '``', $table).'`';
    }

    private function applicationAccount(): string
    {
        $username = config('audit.database.app_username');
        $host = config('audit.database.app_host');

        if (! is_string($username) || preg_match('/\A[A-Za-z0-9_]+\z/', $username) !== 1) {
            throw new RuntimeException('AUDIT_DB_APP_USERNAME doit identifier le compte applicatif MySQL.');
        }

        if (! is_string($host) || preg_match('/\A[A-Za-z0-9_.:%-]+\z/', $host) !== 1) {
            throw new RuntimeException('AUDIT_DB_APP_HOST contient une valeur MySQL invalide.');
        }

        return "'{$username}'@'{$host}'";
    }
};
