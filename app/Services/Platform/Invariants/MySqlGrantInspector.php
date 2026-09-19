<?php

namespace App\Services\Platform\Invariants;

final class MySqlGrantInspector
{
    /**
     * MySQL 8 et MariaDB 10.11 peuvent ajouter des clauses après le destinataire. Seules les
     * parties stables avant TO sont analysées ; une forme inconnue reste un écart explicite.
     *
     * @param  list<string>  $grants
     * @return array{violations: list<string>, unparsed: bool}
     */
    public function inspect(array $grants, string $database): array
    {
        $violations = [];
        $unparsed = false;

        foreach ($grants as $grant) {
            if (preg_match('/^GRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s+/i', $grant, $matches) !== 1) {
                $unparsed = true;

                continue;
            }

            $privileges = trim($matches[1]);
            $scope = trim($matches[2]);

            if ($this->scopeReachesAuditLog($scope, $database)
                && $this->containsForbiddenPrivilege($privileges)) {
                $violations[] = "{$privileges} ON {$scope}";
            }
        }

        return ['violations' => $violations, 'unparsed' => $unparsed];
    }

    private function containsForbiddenPrivilege(string $privileges): bool
    {
        return preg_match('/\bDELETE\b/i', $privileges) === 1
            || preg_match('/\bALL(?:\s+PRIVILEGES)?\b/i', $privileges) === 1;
    }

    private function scopeReachesAuditLog(string $scope, string $database): bool
    {
        $normalizedScope = mb_strtolower(str_replace(['`', '"', "'", ' '], '', $scope));
        $normalizedDatabase = mb_strtolower($database);

        return in_array($normalizedScope, [
            '*.*',
            "{$normalizedDatabase}.*",
            "{$normalizedDatabase}.audit_logs",
        ], true);
    }
}
