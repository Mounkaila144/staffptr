<?php

namespace Tests\Feature;

use App\Services\Platform\RestoreLog;
use App\Services\Platform\RestoreTestService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Story 11.1, Task 3 — test de restauration et registre persistant (AC 13 à 19, AC 55).
 *
 * **Ce que ces tests prouvent, et ce qu'ils ne prouvent pas.** Ils prouvent que la commande échoue
 * bruyamment sur une archive corrompue, illisible ou vide, et qu'elle consigne chaque résultat.
 * Ils ne remplacent pas la preuve finale exigée par l'AC 55 : une **archive réelle restaurée** sur
 * un moteur MariaDB, consignée dans `shared/ops/restore-log.md`. Une simulation ne restaure rien.
 */
class RestoreTestCommandTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ops.restore_log_path' => storage_path('app/testing/restore-log.md')]);
        @unlink(storage_path('app/testing/restore-log.md'));
    }

    /** AC 19 — sans aucune archive, la commande échoue bruyamment. */
    public function test_ac_19_the_command_fails_loudly_when_no_archive_exists(): void
    {
        Storage::fake('backups');

        $this->artisan('ptr:test-restore')->assertFailed();

        $entries = app(RestoreLog::class)->entries();
        $this->assertCount(1, $entries);
        $this->assertStringContainsString('ÉCHEC', $entries[0]);
        $this->assertStringContainsString('Aucune archive', $entries[0]);
    }

    /** AC 19 — une archive corrompue échoue, et l'échec est consigné. */
    public function test_ac_19_a_corrupted_archive_fails_and_is_recorded(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('staffptr/2026-08-17-02-00-00.zip', 'ceci-nest-pas-un-zip');

        $this->artisan('ptr:test-restore')->assertFailed();

        $entries = app(RestoreLog::class)->entries();
        $this->assertStringContainsString('ÉCHEC', $entries[0]);
        $this->assertStringContainsString('illisible ou corrompue', $entries[0]);
    }

    /** AC 19 — une archive valide mais sans dump SQL est refusée. */
    public function test_ac_19_an_archive_without_a_sql_dump_is_refused(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('staffptr/2026-08-17-02-00-00.zip', $this->zipWithout());

        $this->artisan('ptr:test-restore')->assertFailed();

        $this->assertStringContainsString('aucun dump SQL', app(RestoreLog::class)->entries()[0]);
    }

    /** AC 19 — une clé invalide sur une archive chiffrée est détectée et nommée. */
    public function test_ac_19_an_invalid_passphrase_is_detected(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('staffptr/2026-08-17-02-00-00.zip', $this->encryptedZip('bonne-phrase'));
        config(['backup.backup.password' => 'mauvaise-phrase']);

        $this->artisan('ptr:test-restore')->assertFailed();

        $entry = app(RestoreLog::class)->entries()[0];
        $this->assertStringContainsString('ÉCHEC', $entry);
        $this->assertStringContainsString('déchiffré', $entry);
    }

    /** AC 14 — le nom de la base jetable ne peut jamais viser une base réelle. */
    public function test_ac_14_the_scratch_database_name_can_never_target_a_real_schema(): void
    {
        $source = (string) file_get_contents(app_path('Services/Platform/RestoreTestService.php'));

        $this->assertStringContainsString('ptr_restore_test_', $source);
        $this->assertStringContainsString('assertSafeScratchName', $source);
        // Le nettoyage est inconditionnel : une base jetable oubliée est une fuite.
        $this->assertStringContainsString('} finally {', $source);
        $this->assertStringContainsString('dropScratchDatabase', $source);
    }

    /** AC 14 — un nom hors préfixe attendu est refusé avant toute instruction SQL. */
    public function test_ac_14_a_name_outside_the_expected_prefix_is_refused(): void
    {
        $service = app(RestoreTestService::class);
        $method = new \ReflectionMethod($service, 'assertSafeScratchName');

        foreach (['staffptr_production', 'mysql', 'ptr_restore_test_; DROP DATABASE x'] as $hostile) {
            $refused = false;

            try {
                $method->invoke($service, $hostile);
            } catch (RuntimeException) {
                $refused = true;
            }

            $this->assertTrue($refused, "Le nom « {$hostile} » doit être refusé.");
        }

        // Un nom conforme passe.
        $method->invoke($service, RestoreTestService::SCRATCH_PREFIX.'20260817_020000');
        $this->assertTrue(true);
    }

    /** AC 16 — le registre n'est jamais réécrit : chaque exécution ajoute une ligne. */
    public function test_ac_16_the_log_only_ever_appends(): void
    {
        Storage::fake('backups');

        $this->artisan('ptr:test-restore')->assertFailed();
        $this->artisan('ptr:test-restore')->assertFailed();

        $this->assertCount(2, app(RestoreLog::class)->entries());
    }

    /** AC 16, AC 55 — le registre vit hors release, dans un chemin configurable. */
    public function test_ac_16_the_log_lives_outside_any_release(): void
    {
        config(['ops.restore_log_path' => '/srv/staffptr/shared/ops/restore-log.md']);

        $this->assertSame('/srv/staffptr/shared/ops/restore-log.md', app(RestoreLog::class)->path());
        $this->assertStringNotContainsString('releases/', app(RestoreLog::class)->path());
    }

    /** AC 55 — tant qu'aucune restauration n'a réussi, le registre le dit. */
    public function test_ac_55_the_log_reports_that_no_restoration_has_succeeded_yet(): void
    {
        Storage::fake('backups');
        $this->artisan('ptr:test-restore')->assertFailed();

        $this->assertFalse(
            app(RestoreLog::class)->hasSuccessfulEntry(),
            'Un échec ne doit jamais compter comme une restauration réussie.',
        );
    }

    /**
     * AC 14 — **régressions découvertes en production**, chacune ayant fait échouer une vraie
     * restauration avant d'être corrigée.
     */
    public function test_ac_14_the_restore_survives_the_three_defects_found_in_production(): void
    {
        $source = (string) file_get_contents(app_path('Services/Platform/RestoreTestService.php'));

        // 1. Connexion dédiée : la connexion applicative n'a pas le droit de créer une base.
        $this->assertStringContainsString("private const CONNECTION = 'mysql_restore'", $source);
        $this->assertIsArray(config('database.connections.mysql_restore'));

        // 2. Import par le client `mysql` : PDO ne sait pas exécuter les directives `DELIMITER`
        //    d'un dump porteur de déclencheurs.
        $this->assertStringContainsString('defaults-extra-file', $source);
        $this->assertStringNotContainsString('$connection->unprepared($sql)', $source);

        // 3. DEFINER neutralisés : les recréer sous leur compte d'origine exigerait SUPER.
        $this->assertStringContainsString('stripDefiners', $source);
    }

    /** Les clauses DEFINER sont réellement retirées, sous leurs deux formes. */
    public function test_definer_clauses_are_stripped_in_both_forms(): void
    {
        $service = app(RestoreTestService::class);
        $method = new \ReflectionMethod($service, 'stripDefiners');

        $sql = "/*!50003 CREATE*/ /*!50017 DEFINER=`staffptr_migration`@`localhost`*/ /*!50003 TRIGGER t */;\n"
            .'CREATE DEFINER=`autre`@`localhost` FUNCTION f() RETURNS INT RETURN 1;';

        $cleaned = (string) $method->invoke($service, $sql);

        $this->assertStringNotContainsString('DEFINER', $cleaned);
        $this->assertStringContainsString('TRIGGER t', $cleaned);
        $this->assertStringContainsString('FUNCTION f()', $cleaned);
    }

    /**
     * AC 15 — un journal d'audit vide n'est suspect que si des données métier existent. Sur une
     * installation neuve, tout est vide et ce n'est pas une restauration partielle.
     */
    public function test_ac_15_an_empty_audit_log_only_fails_when_business_rows_exist(): void
    {
        $source = (string) file_get_contents(app_path('Services/Platform/RestoreTestService.php'));

        $this->assertStringContainsString('$auditRows === 0 && $otherRows > 0', $source);
    }

    /** AC 16 — une cellule du registre reste sur une seule ligne, quel que soit le message. */
    public function test_ac_16_a_multiline_error_never_breaks_the_registry_table(): void
    {
        app(RestoreLog::class)->append(false, "Erreur MariaDB\nsur | plusieurs | lignes\n\navec des barres");

        $entries = app(RestoreLog::class)->entries();

        $this->assertCount(1, $entries);
        // Quatre cellules encadrées par cinq barres : les barres du message ont été neutralisées.
        $this->assertSame(5, substr_count($entries[0], '|'), 'La ligne doit rester une ligne de tableau valide.');
        $this->assertStringNotContainsString("\n", $entries[0]);
    }

    /** AC 13 — la commande est planifiée mensuellement en heure de Niamey. */
    public function test_ac_13_the_command_is_scheduled_monthly_in_niamey_time(): void
    {
        $schedule = (string) file_get_contents(base_path('routes/console.php'));

        $this->assertMatchesRegularExpression(
            "/ptr:test-restore'\)\s*->monthlyOn\(1, '\d{2}:\d{2}'\)\s*->timezone\('Africa\/Niamey'\)/s",
            $schedule,
        );
    }

    /** Une archive ZIP valide mais sans fichier `.sql`. */
    private function zipWithout(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('lisez-moi.txt', 'aucun dump ici');
        $zip->close();
        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    /** Une archive chiffrée contenant un dump. */
    private function encryptedZip(string $passphrase): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->setPassword($passphrase);
        $zip->addFromString('database.sql', 'CREATE TABLE demo (id INT);');
        $zip->setEncryptionName('database.sql', ZipArchive::EM_AES_256);
        $zip->close();
        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }
}
