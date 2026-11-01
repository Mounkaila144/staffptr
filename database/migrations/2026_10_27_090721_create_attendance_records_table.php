<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des arrivées et départs des stagiaires (story 13.1).
 *
 * Une ligne par stagiaire et par jour. Les heures viennent de l'horloge du serveur, jamais d'une
 * saisie, et sont enregistrées depuis le poste de la direction : un stagiaire ne peut pas déclarer
 * son arrivée depuis chez lui. L'heure attendue est figée sur la ligne, pour qu'un changement de
 * réglage ne réécrive pas les retards passés.
 *
 * `GRANT UPDATE` est accordé au compte applicatif, conformément à `docs/ops/database-users.md` :
 * l'enregistrement du départ complète la ligne de l'arrivée. `DELETE` reste refusé.
 */
return new class extends Migration
{
    /** @var array<string, array{0: mixed, 1: string}> */
    private const SETTINGS = [
        'intern_arrival_time' => ['08:00', 'time'],
        'intern_arrival_tolerance_minutes' => [10, 'integer'],
    ];

    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('work_date');
            $table->timestamp('arrived_at', 3);
            $table->foreignId('arrival_recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('left_at', 3)->nullable();
            $table->foreignId('departure_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('expected_arrival_time', 5);
            $table->unsignedSmallInteger('tolerance_minutes');
            $table->unsignedInteger('late_minutes');
            $table->timestamps(3);
            // Une seule arrivée par stagiaire et par jour, garantie par la base et non par l'écran.
            $table->unique(['user_id', 'work_date']);
            $table->index('work_date');
        });

        // Le réglage doit exister dès le déploiement : son absence lèverait une erreur à la lecture.
        $now = now('UTC');
        foreach (self::SETTINGS as $key => [$value, $type]) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => json_encode($value),
                    'type' => $type,
                    'effective_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Les réglages sont lus depuis un cache : sans cet oubli, l'application ignorerait les
        // deux nouvelles lignes jusqu'à l'expiration du cache et lèverait « paramètre absent ».
        Cache::forget('settings:all');

        $this->grantApplicationUpdate();
    }

    public function down(): void
    {
        $this->revokeApplicationUpdate();
        Schema::dropIfExists('attendance_records');
        DB::table('settings')->whereIn('key', array_keys(self::SETTINGS))->delete();
        Cache::forget('settings:all');
    }

    private function grantApplicationUpdate(): void
    {
        if (! $this->isMysqlFamily()) {
            return;
        }

        DB::unprepared("GRANT UPDATE ON {$this->qualifiedTable('attendance_records')} TO {$this->applicationAccount()}");
    }

    private function revokeApplicationUpdate(): void
    {
        if (! $this->isMysqlFamily() || ! Schema::hasTable('attendance_records')) {
            return;
        }

        DB::unprepared("REVOKE UPDATE ON {$this->qualifiedTable('attendance_records')} FROM {$this->applicationAccount()}");
    }

    private function isMysqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function qualifiedTable(string $table): string
    {
        $database = DB::connection()->getDatabaseName();

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
