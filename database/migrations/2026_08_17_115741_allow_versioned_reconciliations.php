<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rapprochements versionnés : l'unicité porte désormais sur `previous_id`, plus sur la période.
 *
 * **Divergence de moteur corrigée par la story 11.1.** L'index `reconciliation_period_unique`
 * commence par `account_id`, qui porte une clé étrangère. MySQL 8 crée automatiquement un index
 * de remplacement avant d'accepter la suppression ; **MariaDB 10.11 refuse** :
 *
 *     SQLSTATE[HY000]: General error: 1553 Cannot drop index
 *     'reconciliation_period_unique': needed in a foreign key constraint
 *
 * C'est exactement le risque annoncé par DEC-02 — la CI tourne sur MySQL, la production sur
 * MariaDB. Le défaut n'apparaissait qu'au premier déploiement réel.
 *
 * La correction crée explicitement l'index de support sur `account_id` **avant** de supprimer
 * l'unique, ce qui satisfait les deux moteurs. Chaque étape est conditionnée à l'état réel du
 * schéma : la migration est donc rejouable sans erreur sur une base déjà migrée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. L'index de support de la clé étrangère doit exister avant toute suppression.
        if (! $this->indexExists('reconciliations', 'reconciliations_account_id_index')) {
            Schema::table('reconciliations', function (Blueprint $table): void {
                $table->index('account_id', 'reconciliations_account_id_index');
            });
        }

        // 2. L'unique de période peut alors partir, sur MariaDB comme sur MySQL.
        if ($this->indexExists('reconciliations', 'reconciliation_period_unique')) {
            Schema::table('reconciliations', function (Blueprint $table): void {
                $table->dropUnique('reconciliation_period_unique');
            });
        }

        // 3. Une révision remplace exactement un rapprochement : `previous_id` est unique.
        if (! $this->indexExists('reconciliations', 'reconciliation_previous_unique')) {
            Schema::table('reconciliations', function (Blueprint $table): void {
                $table->unique('previous_id', 'reconciliation_previous_unique');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('reconciliations', 'reconciliation_previous_unique')) {
            Schema::table('reconciliations', function (Blueprint $table): void {
                $table->dropUnique('reconciliation_previous_unique');
            });
        }

        if (! $this->indexExists('reconciliations', 'reconciliation_period_unique')) {
            Schema::table('reconciliations', function (Blueprint $table): void {
                $table->unique(
                    ['account_id', 'period_start', 'period_end', 'prepared_by'],
                    'reconciliation_period_unique',
                );
            });
        }

        // L'index de support n'est retiré qu'en dernier, une fois l'unique rétabli : sans cet
        // ordre, MariaDB refuserait à nouveau.
        if ($this->indexExists('reconciliations', 'reconciliations_account_id_index')) {
            Schema::table('reconciliations', function (Blueprint $table): void {
                $table->dropIndex('reconciliations_account_id_index');
            });
        }
    }

    /**
     * SQLite ne connaît pas `information_schema` ; il expose ses index par `PRAGMA`. Le contrôle
     * doit fonctionner sur les deux familles, la suite locale tournant sur SQLite.
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $connection->selectOne(
                'SELECT 1 AS present FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                [$table, $index],
            ) !== null;
        }

        foreach (DB::select("PRAGMA index_list('{$table}')") as $row) {
            if (($row->name ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
