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

        Schema::connection($connectionName)->create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_unread_count_index',
            );
        });

        $this->grantApplicationUpdate($connectionName);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connectionName = $this->connectionName();

        $this->revokeApplicationUpdate($connectionName);
        Schema::connection($connectionName)->dropIfExists('notifications');
    }

    private function grantApplicationUpdate(string $connectionName): void
    {
        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        DB::connection($connectionName)->unprepared(
            "GRANT UPDATE ON {$this->qualifiedTable($connectionName)} TO {$this->applicationAccount()}"
        );
    }

    private function revokeApplicationUpdate(string $connectionName): void
    {
        if (! $this->isMysqlFamily($connectionName)) {
            return;
        }

        DB::connection($connectionName)->unprepared(
            "REVOKE UPDATE ON {$this->qualifiedTable($connectionName)} FROM {$this->applicationAccount()}"
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

    private function qualifiedTable(string $connectionName): string
    {
        $database = DB::connection($connectionName)->getDatabaseName();

        return '`'.str_replace('`', '``', $database).'`.`notifications`';
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
