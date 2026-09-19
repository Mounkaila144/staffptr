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

        Schema::connection($connectionName)->create('user_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('field', 60);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            // DATETIME(3), sans conversion MySQL ni limite 2038 : rétention permanente.
            $table->dateTime('changed_at', precision: 3);
            $table->text('reason')->nullable();
            $table->index(['user_id', 'changed_at'], 'user_history_user_changed_index');
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

        Schema::connection($connectionName)->dropIfExists('user_history');
    }

    private function createMysqlBarriers(string $connectionName): void
    {
        $connection = DB::connection($connectionName);
        $database = $this->quoteIdentifier($connection->getDatabaseName());
        [$username, $host] = $this->applicationAccount();

        $connection->unprepared(<<<'SQL'
            CREATE TRIGGER user_history_prevent_update
            BEFORE UPDATE ON user_history
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'user_history entries are immutable'
            SQL);
        $connection->unprepared(<<<'SQL'
            CREATE TRIGGER user_history_prevent_delete
            BEFORE DELETE ON user_history
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'user_history entries are immutable'
            SQL);
        $connection->unprepared(
            "GRANT SELECT, INSERT ON {$database}.`user_history` TO '{$username}'@'{$host}'"
        );
    }

    private function dropMysqlBarriers(string $connectionName): void
    {
        $connection = DB::connection($connectionName);
        $database = $this->quoteIdentifier($connection->getDatabaseName());
        [$username, $host] = $this->applicationAccount();

        $connection->unprepared('DROP TRIGGER IF EXISTS user_history_prevent_update');
        $connection->unprepared('DROP TRIGGER IF EXISTS user_history_prevent_delete');
        $connection->unprepared(
            "REVOKE SELECT, INSERT ON {$database}.`user_history` FROM '{$username}'@'{$host}'"
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
