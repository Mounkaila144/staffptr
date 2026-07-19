<?php

use App\Enums\UserState;
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
        $driver = DB::connection($connectionName)->getDriverName();

        Schema::connection($connectionName)->create('users', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('phone', 20);
            $table->string('password');
            $table->enum('state', array_column(UserState::cases(), 'value'))
                ->default(UserState::Invite->value);
            $table->boolean('must_change_password')->default(true);
            $table->timestamp('locked_until', precision: 3)->nullable();
            $table->unsignedSmallInteger('failed_attempts')->default(0);

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                $table->string('phone_unique_key', 20)
                    ->nullable()
                    ->virtualAs("CASE WHEN state = 'archive' THEN NULL ELSE phone END");
                $table->unique('phone_unique_key', 'users_phone_active_unique');
            }

            $table->timestamps(precision: 3);
        });

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::connection($connectionName)->unprepared(<<<'SQL'
                ALTER TABLE users
                  ADD COLUMN phone_unique_key VARCHAR(20)
                    GENERATED ALWAYS AS (IF(state = 'archive', NULL, phone)) STORED,
                  ADD UNIQUE INDEX users_phone_active_unique (phone_unique_key)
                SQL);
        }

        $this->grantApplicationUpdate($connectionName);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connectionName = $this->connectionName();

        $this->revokeApplicationUpdate($connectionName);
        Schema::connection($connectionName)->dropIfExists('users');
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

        return '`'.str_replace('`', '``', $database).'`.`users`';
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
