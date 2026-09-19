<?php

namespace Tests\Feature;

use App\Services\Platform\BackupStatus;
use App\Services\Platform\Invariants\BackupFreshnessInvariant;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * Story 11.1, Task 2 — dispositif de sauvegarde (AC 1 à 11, AC 54).
 *
 * Ces tests portent sur le **contrat de configuration**, pas sur le paquet lui-même. Ils
 * détectent la régression qui compte : quelqu'un qui, un jour, ajouterait `.env` aux fichiers
 * sauvegardés ou retirerait le chiffrement.
 */
class BackupConfigurationTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    /** AC 2 — l'archive contient les pièces jointes privées, pas le code source. */
    public function test_ac_2_the_archive_includes_private_attachments_only(): void
    {
        $included = config('backup.backup.source.files.include');

        $this->assertSame([storage_path('app/private')], $included);
    }

    /**
     * AC 7, AC 8 — `.env`, Redis, `storage/logs`, `node_modules` et `vendor` ne sont jamais
     * sauvegardés. Le `.env` est le point critique : le joindre à l'archive reviendrait à ranger
     * la clé dans le coffre qu'elle ouvre.
     */
    public function test_ac_7_and_8_secrets_logs_and_dependencies_are_never_archived(): void
    {
        /** @var list<string> $included */
        $included = config('backup.backup.source.files.include');
        /** @var list<string> $excluded */
        $excluded = config('backup.backup.source.files.exclude');

        // `.env` est hors du périmètre inclus : il vit à la racine, jamais dans `storage/app/private`.
        foreach ($included as $path) {
            $this->assertStringNotContainsString(base_path('.env'), $path);
            $this->assertSame(storage_path('app/private'), $path);
        }

        foreach ([base_path('vendor'), base_path('node_modules'), storage_path('logs')] as $forbidden) {
            $this->assertContains($forbidden, $excluded, "Le chemin « {$forbidden} » doit être exclu.");
        }
    }

    /** AC 3 — l'archive est chiffrée, et la phrase secrète vient de l'environnement. */
    public function test_ac_3_the_archive_is_encrypted_and_the_passphrase_is_never_hard_coded(): void
    {
        $this->assertSame('default', config('backup.backup.encryption'));

        $source = (string) file_get_contents(config_path('backup.php'));
        $this->assertStringContainsString("env('BACKUP_ARCHIVE_PASSPHRASE')", $source);
        // Aucune valeur littérale de phrase secrète dans le dépôt.
        $this->assertDoesNotMatchRegularExpression("/'password'\s*=>\s*'[^']+'/", $source);
    }

    /** AC 2 — le dump est cohérent : `--single-transaction` et sans verrou de tables. */
    public function test_ac_2_the_dump_uses_a_single_transaction_without_locking_tables(): void
    {
        $this->assertTrue(config('database.connections.mysql.dump.use_single_transaction'));
        $this->assertStringContainsString(
            '--skip-lock-tables',
            (string) config('database.connections.mysql.dump.add_extra_option'),
        );
    }

    /** AC 5 — rotation 7 quotidiennes / 4 hebdomadaires / 12 mensuelles. */
    public function test_ac_5_the_rotation_keeps_seven_daily_four_weekly_and_twelve_monthly(): void
    {
        $strategy = config('backup.cleanup.default_strategy');

        $this->assertSame(7, $strategy['keep_daily_backups_for_days']);
        $this->assertSame(4, $strategy['keep_weekly_backups_for_weeks']);
        $this->assertSame(12, $strategy['keep_monthly_backups_for_months']);
    }

    /** AC 4 — une copie locale existe ; la destination hors site attend DEC-06. */
    public function test_ac_4_a_local_copy_exists_and_the_offsite_disk_awaits_dec_06(): void
    {
        $this->assertContains('backups', config('backup.backup.destination.disks'));
        $this->assertIsArray(config('filesystems.disks.backups-offsite'));

        // Sans bucket configuré, la destination hors site n'est pas déclarée : un échec quotidien
        // masquerait les vraies alertes.
        if (config('filesystems.disks.backups-offsite.bucket') === null) {
            $this->assertNotContains('backups-offsite', config('backup.backup.destination.disks'));
        }
    }

    /** AC 6, AC 22 — le seuil de `backup:monitor` est d'un jour, aligné sur le RPO de 24 h. */
    public function test_ac_6_the_monitor_alerts_after_a_single_missed_day(): void
    {
        $checks = config('backup.monitor_backups.0.health_checks');

        $this->assertSame(1, $checks[MaximumAgeInDays::class]);
    }

    /** AC 11 — l'invariant échoue quand aucune sauvegarde n'existe. */
    public function test_ac_11_the_invariant_fails_when_no_backup_exists(): void
    {
        Storage::fake('backups');

        $result = app(BackupFreshnessInvariant::class)->check();

        $this->assertFalse($result->passed);
        $this->assertFalse($result->pending, "Le disque étant configuré, le contrôle n'est plus en attente.");
        $this->assertStringContainsString('aucune sauvegarde', $result->observed);
    }

    /** AC 11 — l'invariant passe avec une sauvegarde fraîche. */
    public function test_ac_11_the_invariant_passes_with_a_fresh_backup(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('staffptr/2026-08-17-02-00-00.zip', 'archive');

        $result = app(BackupFreshnessInvariant::class)->check();

        $this->assertTrue($result->passed);
        $this->assertSame(26, BackupStatus::MAX_AGE_HOURS);
    }

    /** AC 10 — `/up` expose l'âge de la sauvegarde sans révéler chemin, fournisseur ni secret. */
    public function test_ac_10_health_exposes_backup_age_without_any_secret(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('staffptr/2026-08-17-02-00-00.zip', 'archive');

        $response = $this->get('/up');
        $response->assertOk();
        $payload = $response->json();

        $this->assertArrayHasKey('backup', $payload['checks']);
        $this->assertSame('fraiche', $payload['checks']['backup']['state']);
        $this->assertSame(26, $payload['checks']['backup']['max_age_hours']);

        // Rien qui aide à trouver ou ouvrir une archive.
        $body = $response->getContent();
        foreach (['bucket', 'endpoint', 'passphrase', 'password', 'storage/app/backups', 'secret'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, (string) $body);
        }
    }

    /**
     * AC 10 — une sauvegarde absente **dégrade la santé en production**, sans jamais rendre
     * l'application indisponible.
     *
     * Hors production, l'information est exposée mais ne dégrade rien : un poste de développement
     * et une préproduction — qui n'est pas sauvegardée par conception — n'ont rien à sauvegarder.
     */
    public function test_ac_10_a_missing_backup_degrades_health_in_production_only(): void
    {
        Storage::fake('backups');

        // Hors production : signalé, pas dégradé.
        $response = $this->get('/up');
        $response->assertOk();
        $this->assertSame('absente', $response->json('checks.backup.state'));
        $this->assertSame('ok', $response->json('status'));

        // En production : dégradé, mais toujours 200 — l'application fonctionne, elle n'est
        // simplement plus protégée.
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $production = $this->get('/up');
            $production->assertOk();
            $this->assertSame('degraded', $production->json('status'));
            $this->assertSame('absente', $production->json('checks.backup.state'));
        } finally {
            app()->detectEnvironment(static fn (): string => 'testing');
        }
    }

    /**
     * AC 2 — **régression découverte en production** : la sauvegarde utilise une connexion
     * dédiée, dotée du privilège `TRIGGER`.
     *
     * Sans ce privilège, `mysqldump` omet **silencieusement** les déclencheurs. L'archive se
     * restaure alors sans les barrières d'immuabilité du journal d'audit et du module financier —
     * un défaut invisible jusqu'au jour de la restauration.
     */
    public function test_ac_2_the_dump_uses_a_dedicated_connection_able_to_read_triggers(): void
    {
        $this->assertSame(['mysql_backup'], config('backup.backup.source.databases'));
        $this->assertIsArray(config('database.connections.mysql_backup'));

        $options = (string) config('database.connections.mysql_backup.dump.add_extra_option');
        $this->assertStringContainsString('--triggers', $options);
        $this->assertStringContainsString('--routines', $options);

        // `--events` exige le privilège EVENT, que l'application n'utilise pas : le demander
        // élargirait le rôle sans rien apporter.
        $this->assertStringNotContainsString('--events', $options);
    }

    /**
     * AC 4 — **régression découverte en production** : une variable déclarée vide vaut chaîne
     * vide, pas `null`.
     *
     * Ne tester que `null` faisait passer `BACKUP_OBJECT_BUCKET=` pour une configuration valide,
     * et la sauvegarde tentait d'écrire sur un S3 sans bucket.
     */
    public function test_ac_4_an_empty_bucket_variable_counts_as_unconfigured(): void
    {
        $source = (string) file_get_contents(config_path('backup.php'));

        $this->assertStringContainsString(
            "in_array(env('BACKUP_OBJECT_BUCKET'), [null, ''], true)",
            $source,
        );

        config(['filesystems.disks.backups-offsite.bucket' => '']);
        $this->assertFalse(app(BackupStatus::class)->offsiteConfigured());
    }

    /** AC 1 — la sauvegarde est planifiée à 02 h 00 heure de Niamey. */
    public function test_ac_1_the_backup_runs_at_two_in_the_morning_niamey_time(): void
    {
        $schedule = (string) file_get_contents(base_path('routes/console.php'));

        $this->assertMatchesRegularExpression(
            "/backup:run'\)\s*->dailyAt\('02:00'\)\s*->timezone\('Africa\/Niamey'\)/s",
            $schedule,
        );
        $this->assertStringContainsString('backup:monitor', $schedule);
        $this->assertStringContainsString('backup:clean', $schedule);
    }
}
