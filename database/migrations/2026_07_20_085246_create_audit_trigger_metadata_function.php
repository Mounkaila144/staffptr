<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var bool */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection($this->connectionName());

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // information_schema.TRIGGERS exige le privilège TRIGGER, lequel autoriserait aussi le
        // compte applicatif à supprimer les barrières. Cette fonction à droits du définisseur
        // n'expose qu'une réponse booléenne bornée aux déclencheurs du journal d'audit.
        $databaseName = $connection->getDatabaseName();
        $quotedDatabase = $connection->getPdo()->quote($databaseName);
        $database = $this->quoteIdentifier($databaseName);
        [$username, $host] = $this->applicationAccount();

        if (! is_string($quotedDatabase)) {
            throw new RuntimeException("Le schéma courant n'a pas pu être cité pour la fonction d'invariants.");
        }

        $connection->unprepared("DROP FUNCTION IF EXISTS {$database}.`ptr_audit_trigger_exists`");
        $connection->unprepared(<<<MYSQL
            CREATE FUNCTION {$database}.`ptr_audit_trigger_exists`(trigger_name_to_check VARCHAR(64))
            RETURNS TINYINT
            NOT DETERMINISTIC
            READS SQL DATA
            SQL SECURITY DEFINER
            RETURN EXISTS (
                SELECT 1
                FROM information_schema.TRIGGERS
                WHERE TRIGGER_SCHEMA = {$quotedDatabase}
                  AND EVENT_OBJECT_TABLE = 'audit_logs'
                  AND LOWER(TRIGGER_NAME) = LOWER(trigger_name_to_check)
            )
            MYSQL);
        $connection->unprepared(
            "GRANT EXECUTE ON FUNCTION {$database}.`ptr_audit_trigger_exists` TO '{$username}'@'{$host}'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection($this->connectionName());

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $database = $this->quoteIdentifier($connection->getDatabaseName());

        $connection->unprepared("DROP FUNCTION IF EXISTS {$database}.`ptr_audit_trigger_exists`");
    }

    private function connectionName(): string
    {
        return $this->getConnection() ?? (string) config('database.default');
    }

    /** @return array{string, string} */
    private function applicationAccount(): array
    {
        $username = config('audit.database.app_username');
        $host = config('audit.database.app_host');

        if (! is_string($username) || preg_match('/\A[A-Za-z0-9_]+\z/', $username) !== 1) {
            throw new RuntimeException('AUDIT_DB_APP_USERNAME doit identifier le compte applicatif MySQL.');
        }

        if (! is_string($host) || preg_match('/\A[A-Za-z0-9_.:%-]+\z/', $host) !== 1) {
            throw new RuntimeException('AUDIT_DB_APP_HOST contient une valeur MySQL invalide.');
        }

        return [$username, $host];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
