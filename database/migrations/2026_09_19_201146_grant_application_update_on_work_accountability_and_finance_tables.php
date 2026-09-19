<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattrape les `GRANT UPDATE` omis par les migrations des epics 4 à 11.
 *
 * La convention du projet veut que la migration qui crée une table métier accorde
 * elle-même `UPDATE` au compte applicatif : `SELECT` et `INSERT` sont hérités du
 * schéma, `UPDATE` ne l'est jamais (`docs/ops/database-users.md`). Les migrations
 * des epics 2 et 3 le font ; les quatorze migrations d'août 2026 l'ont oublié, si
 * bien que toute écriture — et jusqu'au moindre `SELECT ... FOR UPDATE`, qui exige
 * ce privilège — était refusée sous le compte restreint.
 *
 * Ces migrations ayant déjà été appliquées en production, la mise à jour de la
 * matrice passe par une migration de rattrapage plutôt que par leur réécriture.
 * `GRANT` est idempotent : la rejouer ne coûte rien.
 *
 * Restent volontairement absentes de cette liste, et doivent le rester :
 * `account_movements` et les tables de versions ou d'historiques, immuables par
 * conception, ainsi que `audit_logs` et `user_history`, dont le refus d'`UPDATE`
 * est le cœur de la piste d'audit. Aucun `DELETE` n'est accordé ici.
 */
return new class extends Migration
{
    /**
     * Tables métier des epics 4 à 11 que l'application modifie.
     *
     * @var list<string>
     */
    private const TABLES = [
        // Epic 4 — travail : projets, tâches, livrables, objectifs.
        'projects',
        'project_members',
        'work_tasks',
        'work_comments',
        'work_links',
        'deliverables',
        'objectives',
        'company_priorities',
        // Epic 5 et 6 — redevabilité : rapports quotidiens, blocages, demandes.
        'daily_reports',
        'daily_report_comments',
        'daily_report_decisions',
        'blockers',
        'task_requests',
        'improvement_plans',
        'improvement_plan_actions',
        'support_request_batches',
        'support_request_batch_items',
        // Epic 6 — stages et revues.
        'internships',
        'internship_plans',
        'internship_checklist_items',
        'internship_evaluations',
        'internship_intake_forms',
        'internship_intake_outcomes',
        'tutor_support_slots',
        'weekly_reviews',
        'weekly_review_objectives',
        // Epic 7 et 8 — finance.
        'accounts',
        'clients',
        'contracts',
        'contract_executors',
        'invoices',
        'payments',
        'fixed_charges',
        'monthly_budgets',
        'monthly_reports',
        'month_closures',
        'reconciliations',
        'share_entitlements',
        'correction_plans',
        'alert_level_states',
        'receipt_sequences',
        // `reserve_movements` est immuable par trigger, mais `SELECT ... FOR UPDATE`
        // exige malgré tout ce privilège : le trigger reste la garde réelle.
        'reserve_movements',
        // Epic 10 — listes et filtres enregistrés.
        'saved_filters',
    ];

    public function up(): void
    {
        $connectionName = $this->connectionName();

        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::connection($connectionName)->hasTable($table)) {
                continue;
            }

            DB::connection($connectionName)->unprepared(
                "GRANT UPDATE ON {$this->qualifiedTable($connectionName, $table)} TO {$this->applicationAccount()}"
            );
        }
    }

    public function down(): void
    {
        $connectionName = $this->connectionName();

        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::connection($connectionName)->hasTable($table)) {
                continue;
            }

            DB::connection($connectionName)->unprepared(
                "REVOKE UPDATE ON {$this->qualifiedTable($connectionName, $table)} FROM {$this->applicationAccount()}"
            );
        }
    }

    private function connectionName(): string
    {
        return $this->getConnection() ?? (string) config('database.default');
    }

    private function isMysqlFamily(string $connectionName): bool
    {
        return in_array(DB::connection($connectionName)->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function qualifiedTable(string $connectionName, string $table): string
    {
        $database = DB::connection($connectionName)->getDatabaseName();

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
