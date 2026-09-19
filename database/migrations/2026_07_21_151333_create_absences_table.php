<?php

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connectionName = $this->connectionName();

        Schema::connection($connectionName)->create('absences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', array_column(AbsenceType::cases(), 'value'));
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 255);
            $table->enum('state', array_column(AbsenceState::cases(), 'value'))->default(AbsenceState::Demandee->value);
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at', precision: 3)->nullable();
            $table->timestamps(precision: 3);
            $table->index(['user_id', 'start_date', 'end_date']);
            $table->index(['state', 'start_date']);
        });

        $this->grantApplicationUpdate($connectionName);
    }

    public function down(): void
    {
        $connectionName = $this->connectionName();

        $this->revokeApplicationUpdate($connectionName);
        Schema::connection($connectionName)->dropIfExists('absences');
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

        return '`'.str_replace('`', '``', $database).'`.`absences`';
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
