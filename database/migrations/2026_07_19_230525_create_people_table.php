<?php

use App\Enums\PersonOperationalStatus;
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

        Schema::connection($connectionName)->create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 160);
            $table->enum(
                'operational_status',
                array_column(PersonOperationalStatus::cases(), 'value'),
            )->default(PersonOperationalStatus::Actif->value);
            $table->date('first_seen_at');
            $table->timestamps(precision: 3);
        });

        $this->grantApplicationUpdate($connectionName, 'people');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connectionName = $this->connectionName();

        $this->revokeApplicationUpdate($connectionName, 'people');
        Schema::connection($connectionName)->dropIfExists('people');
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
        return in_array(
            DB::connection($connectionName)->getDriverName(),
            ['mysql', 'mariadb'],
            true,
        );
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
