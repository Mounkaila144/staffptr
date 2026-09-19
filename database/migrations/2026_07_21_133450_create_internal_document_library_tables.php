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

        Schema::connection($connectionName)->create('internal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('requires_acknowledgement')->default(false);
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->timestamps(precision: 3);
        });

        Schema::connection($connectionName)->create('internal_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internal_document_id')->constrained('internal_documents')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('body')->nullable();
            $table->date('effective_date');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('published_at', precision: 3);
            $table->timestamps(precision: 3);
            $table->unique(['internal_document_id', 'version_number'], 'internal_document_version_unique');
        });

        Schema::connection($connectionName)->table('internal_documents', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('internal_document_versions')
                ->restrictOnDelete();
        });

        Schema::connection($connectionName)->create('internal_document_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('internal_document_version_id')
                ->constrained(
                    table: 'internal_document_versions',
                    indexName: 'internal_document_ack_version_foreign',
                )
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('acknowledged_at', precision: 3);
            $table->timestamps(precision: 3);
            $table->unique(
                ['internal_document_version_id', 'user_id'],
                'internal_document_acknowledgement_unique',
            );
        });

        $this->grantApplicationUpdate($connectionName, 'internal_documents');
        $this->createImmutableBarriers($connectionName, 'internal_document_versions');
        $this->createImmutableBarriers($connectionName, 'internal_document_acknowledgements');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connectionName = $this->connectionName();

        $this->dropImmutableBarriers($connectionName, 'internal_document_acknowledgements');
        $this->dropImmutableBarriers($connectionName, 'internal_document_versions');
        $this->revokeApplicationUpdate($connectionName, 'internal_documents');

        Schema::connection($connectionName)->dropIfExists('internal_document_acknowledgements');
        Schema::connection($connectionName)->table('internal_documents', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });
        Schema::connection($connectionName)->dropIfExists('internal_document_versions');
        Schema::connection($connectionName)->dropIfExists('internal_documents');
    }

    private function grantApplicationUpdate(string $connectionName, string $table): void
    {
        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        DB::connection($connectionName)->unprepared(
            "GRANT UPDATE ON {$this->qualifiedTable($connectionName, $table)} TO {$this->applicationAccount()}"
        );
    }

    private function revokeApplicationUpdate(string $connectionName, string $table): void
    {
        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        DB::connection($connectionName)->unprepared(
            "REVOKE UPDATE ON {$this->qualifiedTable($connectionName, $table)} FROM {$this->applicationAccount()}"
        );
    }

    private function createImmutableBarriers(string $connectionName, string $table): void
    {
        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        $connection = DB::connection($connectionName);
        $triggerPrefix = $table.'_prevent';
        $connection->unprepared(<<<SQL
            CREATE TRIGGER {$triggerPrefix}_update
            BEFORE UPDATE ON {$table}
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = '{$table} entries are immutable'
            SQL);
        $connection->unprepared(<<<SQL
            CREATE TRIGGER {$triggerPrefix}_delete
            BEFORE DELETE ON {$table}
            FOR EACH ROW
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = '{$table} entries are immutable'
            SQL);
        $connection->unprepared(
            "GRANT SELECT, INSERT ON {$this->qualifiedTable($connectionName, $table)} TO {$this->applicationAccount()}"
        );
    }

    private function dropImmutableBarriers(string $connectionName, string $table): void
    {
        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        $connection = DB::connection($connectionName);
        $triggerPrefix = $table.'_prevent';
        $connection->unprepared("DROP TRIGGER IF EXISTS {$triggerPrefix}_update");
        $connection->unprepared("DROP TRIGGER IF EXISTS {$triggerPrefix}_delete");
        $connection->unprepared(
            "REVOKE SELECT, INSERT ON {$this->qualifiedTable($connectionName, $table)} FROM {$this->applicationAccount()}"
        );
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
