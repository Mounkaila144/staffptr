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

        Schema::connection($connectionName)->create('login_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('phone_attempted', 64);
            $table->boolean('successful');
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('occurred_at', precision: 3);
            $table->timestamp('lock_expires_at', precision: 3)->nullable();

            $table->index(['user_id', 'occurred_at'], 'login_attempts_user_period_index');
            $table->index(['phone_attempted', 'occurred_at'], 'login_attempts_phone_period_index');
            $table->index(['ip_address', 'occurred_at'], 'login_attempts_ip_period_index');
            $table->index(['successful', 'occurred_at'], 'login_attempts_result_period_index');
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
        Schema::connection($connectionName)->dropIfExists('login_attempts');
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
        return in_array(
            DB::connection($connectionName)->getDriverName(),
            ['mysql', 'mariadb'],
            true,
        );
    }

    private function qualifiedTable(string $connectionName): string
    {
        $database = DB::connection($connectionName)->getDatabaseName();

        return '`'.str_replace('`', '``', $database).'`.`login_attempts`';
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
