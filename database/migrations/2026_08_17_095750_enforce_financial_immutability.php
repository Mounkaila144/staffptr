<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'accounts',
        'fixed_charges',
        'clients',
        'contracts',
        'contract_executors',
        'invoices',
        'payments',
        'account_movements',
        'share_entitlements',
        'reserve_movements',
        'reconciliations',
        'monthly_budgets',
        'monthly_reports',
        'month_closures',
        'expenses',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->createDeleteTrigger($table);
        }

        $this->createImmutableUpdateTrigger('account_movements');
        $this->createImmutableUpdateTrigger('reserve_movements');
        $this->createStateImmutableTrigger('reconciliations', 'validated');
        $this->createStateImmutableTrigger('monthly_reports', 'validated');
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS finance_no_delete_{$table}");
        }

        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_account_movements');
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_reserve_movements');
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_validated_reconciliations');
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_validated_monthly_reports');
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
        $message = "Une écriture validée de {$table} est immuable.";

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

    private function createStateImmutableTrigger(string $table, string $state): void
    {
        $name = "finance_no_update_validated_{$table}";
        $message = "Une ligne validée de {$table} est immuable.";

        if ($this->isMysqlFamily()) {
            DB::unprepared(
                "CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF OLD.state = '{$state}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE UPDATE ON {$table} WHEN OLD.state = '{$state}' BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }

    private function isMysqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
