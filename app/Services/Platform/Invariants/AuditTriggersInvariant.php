<?php

namespace App\Services\Platform\Invariants;

use Illuminate\Support\Facades\DB;
use Throwable;

class AuditTriggersInvariant implements InvariantCheck
{
    private const NAME = "Déclencheurs d'immuabilité du journal d'audit";

    /** @var list<string> */
    private const EXPECTED_TRIGGERS = [
        'audit_logs_prevent_delete',
        'audit_logs_prevent_update',
    ];

    public function check(): InvariantResult
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return InvariantResult::fail(
                self::NAME,
                "moteur {$driver} non vérifiable",
                'MySQL 8 ou MariaDB 10.11 avec deux déclencheurs présents',
            );
        }

        try {
            // La fonction SQL SECURITY DEFINER lit information_schema.TRIGGERS sans accorder au
            // compte applicatif le privilège TRIGGER qui permettrait de supprimer les barrières.
            $triggers = [];

            foreach (self::EXPECTED_TRIGGERS as $trigger) {
                $row = $connection->selectOne(
                    'SELECT ptr_audit_trigger_exists(?) AS trigger_exists',
                    [$trigger],
                );

                if (is_object($row) && (int) ($row->trigger_exists ?? 0) === 1) {
                    $triggers[] = $trigger;
                }
            }
        } catch (Throwable) {
            return InvariantResult::fail(
                self::NAME,
                'métadonnées du serveur illisibles avec le compte applicatif',
                'deux déclencheurs présents et lisibles',
            );
        }

        $observed = $triggers === [] ? 'aucun déclencheur' : implode(', ', $triggers);
        $expected = implode(', ', self::EXPECTED_TRIGGERS);

        return $triggers === self::EXPECTED_TRIGGERS
            ? InvariantResult::pass(self::NAME, $observed, $expected)
            : InvariantResult::fail(self::NAME, $observed, $expected);
    }
}
