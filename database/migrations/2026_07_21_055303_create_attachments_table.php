<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connectionName = $this->connectionName();

        Schema::connection($connectionName)->create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('attachable');
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->string('thumbnail_path', 500)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps(precision: 3);
        });

        $this->grantApplicationUpdate($connectionName, 'attachments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connectionName = $this->connectionName();

        $this->revokeApplicationUpdate($connectionName, 'attachments');
        Schema::connection($connectionName)->dropIfExists('attachments');
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
