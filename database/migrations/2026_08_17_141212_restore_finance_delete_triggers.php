<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rétablit les déclencheurs anti-suppression perdus par une reconstruction de table
 * (règle métier bloquante n° 7, RM-17, CA-12).
 *
 * **Le défaut corrigé.** La migration `2026_08_17_111653_extend_payments_and_share_entitlements_for_lifecycle`
 * ajoute des colonnes à `payments` et `share_entitlements`. Sous SQLite, ajouter une clé étrangère
 * ou un index unique impose de **reconstruire la table** : Laravel recrée la table, y recopie les
 * données, et les déclencheurs attachés à l'ancienne disparaissent silencieusement. Sous MySQL,
 * `ALTER TABLE ADD COLUMN` conserve les déclencheurs — la barrière tient donc en production, mais
 * pas sur le moteur de développement.
 *
 * C'est exactement le piège annoncé par DEC-02 : « SQLite ne teste pas ce qui compte ». Un test
 * local passait sans que la barrière existe.
 *
 * **Pourquoi une nouvelle migration.** SOC-04 interdit de modifier une migration déployée. La
 * réparation est donc additive et **idempotente** : chaque déclencheur est supprimé s'il existe
 * puis recréé, ce qui rend la migration rejouable et sans effet sur une base déjà saine.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = ['payments', 'share_entitlements'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->recreateDeleteTrigger($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS finance_no_delete_{$table}");
        }
    }

    private function recreateDeleteTrigger(string $table): void
    {
        $name = "finance_no_delete_{$table}";
        $message = "La suppression physique de {$table} est interdite.";

        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared(
                "CREATE TRIGGER {$name} BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }
};
