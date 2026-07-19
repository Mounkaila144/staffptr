<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var bool */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connectionName = $this->connectionName();

        Schema::connection($connectionName)->create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 120);
            // DATETIME(3) et non TIMESTAMP(3) : TIMESTAMP s'arrête au 19/01/2038 alors que le
            // journal d'audit est en rétention permanente, et il est converti selon le fuseau de
            // session MySQL — une restauration sur un serveur configuré autrement décalerait tous
            // les horodatages de la table dont le rôle est précisément d'être opposable (NFR23).
            $table->dateTime('occurred_at', precision: 3);
            $table->string('auditable_type', 120);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('action', 60);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->binary('ip_address', 16)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64);

            $table->index(
                ['auditable_type', 'auditable_id'],
                'audit_logs_auditable_lookup_index',
            );
            $table->index(
                ['actor_id', 'occurred_at'],
                'audit_logs_actor_occurred_index',
            );
            $table->index('occurred_at', 'audit_logs_occurred_at_index');
            $table->index('request_id', 'audit_logs_request_id_index');
        });

        if ($this->driver($connectionName) === 'mysql') {
            $this->createMysqlBarriers($connectionName);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connectionName = $this->connectionName();

        if ($this->driver($connectionName) === 'mysql') {
            $this->dropMysqlBarriers($connectionName);
        }

        Schema::connection($connectionName)->dropIfExists('audit_logs');
    }

    private function createMysqlBarriers(string $connectionName): void
    {
        $connection = DB::connection($connectionName);
        $database = $this->quoteIdentifier($connection->getDatabaseName());
        [$username, $host] = $this->applicationAccount();

        $connection->unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_prevent_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'audit_logs entries are immutable'
            SQL);
        $connection->unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_prevent_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'audit_logs entries are immutable'
            SQL);
        $connection->unprepared(
            "GRANT SELECT, INSERT ON {$database}.`audit_logs` TO '{$username}'@'{$host}'"
        );
    }

    private function dropMysqlBarriers(string $connectionName): void
    {
        $connection = DB::connection($connectionName);
        $database = $this->quoteIdentifier($connection->getDatabaseName());
        [$username, $host] = $this->applicationAccount();

        $connection->unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
        $connection->unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
        $connection->unprepared(
            "REVOKE SELECT, INSERT ON {$database}.`audit_logs` FROM '{$username}'@'{$host}'"
        );
    }

    private function connectionName(): string
    {
        return $this->getConnection() ?? (string) config('database.default');
    }

    private function driver(string $connectionName): string
    {
        return DB::connection($connectionName)->getDriverName();
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
