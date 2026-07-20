<?php

namespace App\Services\Platform\Invariants;

use Illuminate\Support\Facades\DB;
use Throwable;

class AuditDeletePrivilegeInvariant implements InvariantCheck
{
    private const NAME = "Privilège DELETE du journal d'audit";

    public function __construct(private readonly MySqlGrantInspector $grantInspector) {}

    public function check(): InvariantResult
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return InvariantResult::fail(
                self::NAME,
                "moteur {$driver} non vérifiable",
                'aucun DELETE ni ALL PRIVILEGES atteignant audit_logs',
            );
        }

        try {
            $rows = $connection->select('SHOW GRANTS FOR CURRENT_USER()');
        } catch (Throwable) {
            return InvariantResult::fail(
                self::NAME,
                'privilèges du compte applicatif illisibles',
                'aucun DELETE ni ALL PRIVILEGES atteignant audit_logs',
            );
        }

        $grants = [];
        $unreadableRow = false;

        foreach ($rows as $row) {
            $grant = collect((array) $row)
                ->first(static fn (mixed $value): bool => is_string($value));

            if (! is_string($grant)) {
                $unreadableRow = true;

                continue;
            }

            $grants[] = $grant;
        }

        $inspection = $this->grantInspector->inspect($grants, $connection->getDatabaseName());

        if ($unreadableRow || $inspection['unparsed']) {
            return InvariantResult::fail(
                self::NAME,
                'au moins une ligne SHOW GRANTS a un format MySQL/MariaDB non reconnu',
                'toutes les lignes analysées et aucun DELETE ni ALL PRIVILEGES atteignant audit_logs',
            );
        }

        return $inspection['violations'] === []
            ? InvariantResult::pass(
                self::NAME,
                "aucun privilège interdit n'atteint audit_logs",
                'aucun DELETE ni ALL PRIVILEGES atteignant audit_logs',
            )
            : InvariantResult::fail(
                self::NAME,
                implode(' ; ', $inspection['violations']),
                'aucun DELETE ni ALL PRIVILEGES atteignant audit_logs',
            );
    }
}
