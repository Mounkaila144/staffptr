<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Test de restauration en base jetable (story 11.1, AC 13 à 19, NFR25).
 *
 * **Pourquoi ce service existe.** Une sauvegarde non restaurée n'est pas une sauvegarde, c'est un
 * fichier. Ce service prend la dernière archive, la déchiffre, la restaure dans une base
 * **jetable** et vérifie que les données attendues y sont — puis détruit la base.
 *
 * Trois propriétés gouvernent l'écriture :
 *
 * 1. **Échec bruyant** (AC 19). Une archive corrompue, une clé invalide ou un dump illisible
 *    lèvent une exception nommée. Le silence est le pire résultat possible : il laisserait croire
 *    à une protection qui n'existe pas.
 * 2. **Nettoyage garanti** (AC 14). La base jetable est supprimée en `finally`, succès ou échec.
 *    Une base de restauration oubliée sur un VPS partagé est une fuite de données.
 * 3. **Invariants cumulatifs** (AC 15). Le périmètre du produit grandit jalon par jalon ; les
 *    contrôles ne portent que sur les tables **réellement présentes** dans l'archive, sinon une
 *    sauvegarde du Jalon 1 échouerait sur des tables du Jalon 4 qui n'existaient pas encore.
 */
final readonly class RestoreTestService
{
    /** Préfixe non ambigu : personne ne doit confondre cette base avec staging ou production. */
    public const SCRATCH_PREFIX = 'ptr_restore_test_';

    /**
     * Connexion dédiée, dotée des droits de schéma limités au motif `ptr_restore_test_%`.
     *
     * La connexion applicative n'a **pas** le droit de créer une base, et c'est voulu : une
     * commande d'exploitation ne doit jamais emprunter les droits du service qui sert les pages.
     */
    private const CONNECTION = 'mysql_restore';

    /**
     * Contrôles cumulatifs, du socle vers les jalons ultérieurs. Chacun ne s'exécute que si sa
     * table est présente dans l'archive restaurée.
     *
     * @var array<string, string>
     */
    private const CUMULATIVE_CHECKS = [
        'audit_logs' => "le journal d'audit",
        'users' => 'les comptes',
        'people' => 'les personnes',
        'expenses' => 'les dépenses',
        'expense_approvals' => 'les approbations de dépense',
        'payments' => 'les encaissements',
        'account_movements' => 'les mouvements de compte',
    ];

    /**
     * Exécute le test complet et retourne son compte rendu.
     *
     * @return array{archive: string, disk: string, started_at: string, duration_seconds: int, tables: int, checks: list<array{name: string, rows: int}>, scratch_database: string}
     */
    public function run(): array
    {
        $startedAt = CarbonImmutable::now('UTC');
        [$disk, $archive] = $this->latestArchive();
        $extracted = $this->extractDump($disk, $archive);
        $scratch = self::SCRATCH_PREFIX.$startedAt->format('Ymd_His');

        try {
            $this->createScratchDatabase($scratch);
            $this->importDump($scratch, $extracted);
            $tables = $this->tablesIn($scratch);
            $checks = $this->runCumulativeChecks($scratch, $tables);

            return [
                'archive' => basename($archive),
                'disk' => $disk,
                'started_at' => $startedAt->toIso8601String(),
                'duration_seconds' => (int) $startedAt->diffInSeconds(CarbonImmutable::now('UTC'), absolute: true),
                'tables' => count($tables),
                'checks' => $checks,
                'scratch_database' => $scratch,
            ];
        } finally {
            // Nettoyage inconditionnel : une base jetable oubliée est une fuite (AC 14).
            $this->dropScratchDatabase($scratch);
            @unlink($extracted);
            @rmdir(dirname($extracted));
        }
    }

    /**
     * Archive la plus récente, hors site en priorité : c'est elle qui compte le jour où le serveur
     * a disparu. La copie locale ne sert qu'à défaut.
     *
     * @return array{0: string, 1: string}
     */
    public function latestArchive(): array
    {
        $candidates = [];

        foreach (['backups-offsite', 'backups'] as $disk) {
            if (! is_array(config('filesystems.disks.'.$disk))) {
                continue;
            }

            // Une variable déclarée vide vaut chaîne vide, pas `null` : les deux comptent comme
            // « non configuré ».
            if ($disk === 'backups-offsite'
                && in_array(config('filesystems.disks.backups-offsite.bucket'), [null, ''], true)) {
                continue;
            }

            try {
                foreach (Storage::disk($disk)->allFiles() as $file) {
                    if (str_ends_with($file, '.zip')) {
                        $candidates[] = [$disk, $file, Storage::disk($disk)->lastModified($file)];
                    }
                }
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Le stockage « {$disk} » est injoignable : la restauration ne peut pas être testée.",
                    previous: $exception,
                );
            }
        }

        if ($candidates === []) {
            throw new RuntimeException('Aucune archive de sauvegarde à restaurer.');
        }

        usort($candidates, static fn (array $a, array $b): int => $b[2] <=> $a[2]);

        return [$candidates[0][0], $candidates[0][1]];
    }

    /**
     * Déchiffre l'archive et en extrait le dump SQL. Toute anomalie — archive illisible, clé
     * invalide, dump absent — lève une exception nommée (AC 19).
     */
    private function extractDump(string $disk, string $archive): string
    {
        $workDirectory = storage_path('app/restore-test/'.bin2hex(random_bytes(6)));

        if (! is_dir($workDirectory) && ! mkdir($workDirectory, 0700, true) && ! is_dir($workDirectory)) {
            throw new RuntimeException("Le répertoire de travail de restauration n'a pas pu être créé.");
        }

        $localCopy = $workDirectory.'/archive.zip';
        file_put_contents($localCopy, Storage::disk($disk)->get($archive));

        $zip = new ZipArchive;

        if ($zip->open($localCopy) !== true) {
            @unlink($localCopy);
            throw new RuntimeException("L'archive « {$archive} » est illisible ou corrompue.");
        }

        $passphrase = (string) config('backup.backup.password');

        if ($passphrase !== '') {
            $zip->setPassword($passphrase);
        }

        $dumpEntry = null;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_ends_with($name, '.sql')) {
                $dumpEntry = $name;
                break;
            }
        }

        if ($dumpEntry === null) {
            $zip->close();
            @unlink($localCopy);
            throw new RuntimeException("L'archive « {$archive} » ne contient aucun dump SQL.");
        }

        $contents = @$zip->getFromName($dumpEntry);
        $zip->close();
        @unlink($localCopy);

        if ($contents === false || $contents === '') {
            throw new RuntimeException(
                "Le dump de « {$archive} » n'a pas pu être déchiffré : phrase secrète invalide ou archive corrompue.",
            );
        }

        $dumpPath = $workDirectory.'/database.sql';
        file_put_contents($dumpPath, $this->stripDefiners($contents));

        return $dumpPath;
    }

    /**
     * Retire les clauses `DEFINER` du dump.
     *
     * **Pourquoi c'est indispensable.** `mysqldump` inscrit dans chaque déclencheur et chaque
     * routine le compte qui l'a créé — ici `staffptr_migration`. Les recréer sous ce nom exige le
     * privilège `SUPER`, que le rôle de restauration ne doit surtout pas porter :
     *
     *     ERROR 1227 (42000): Access denied; you need (at least one of) the SUPER,
     *     SET USER privilege(s) for this operation.
     *
     * Deux réponses étaient possibles : élargir les droits du rôle de restauration, ou neutraliser
     * les DEFINER. La première rendrait un compte d'exploitation capable de tout faire sur toute
     * l'instance — y compris sur les quatre autres applications du VPS. La seconde est retenue.
     *
     * L'effet est celui voulu : les objets sont recréés au nom du compte qui restaure. Une archive
     * ne doit pas n'être restaurable que par le compte exact qui l'a produite — ce serait une
     * archive fragile.
     *
     * La procédure manuelle de `docs/ops/restore-procedure.md` applique le même retrait.
     */
    private function stripDefiners(string $sql): string
    {
        return (string) preg_replace(
            [
                '#/\*!5000\d DEFINER=[^*]+\*/#',
                '#DEFINER=`[^`]*`@`[^`]*`#',
            ],
            '',
            $sql,
        );
    }

    private function createScratchDatabase(string $name): void
    {
        $this->assertSafeScratchName($name);
        DB::connection(self::CONNECTION)->statement(
            "CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        );
    }

    private function dropScratchDatabase(string $name): void
    {
        try {
            $this->assertSafeScratchName($name);
            DB::connection(self::CONNECTION)->statement("DROP DATABASE IF EXISTS `{$name}`");
        } catch (Throwable) {
            // Le nettoyage ne doit jamais masquer l'erreur d'origine ; il est journalisé par
            // l'appelant via le registre de restauration.
        }
    }

    /**
     * Importe le dump avec le **client `mysql`**, pas avec PDO.
     *
     * Ce n'est pas un détail : un dump porteur de déclencheurs contient des directives
     * `DELIMITER`, qui sont des instructions du client et non du SQL. PDO les rejette, et l'import
     * échouerait précisément sur les déclencheurs d'immuabilité — c'est-à-dire sur ce que la
     * restauration doit le plus sûrement rétablir.
     *
     * Les identifiants passent par un fichier d'options temporaire en 0600, jamais sur la ligne de
     * commande où `ps` les exposerait à tout utilisateur de la machine.
     */
    private function importDump(string $database, string $dumpPath): void
    {
        if (trim((string) file_get_contents($dumpPath)) === '') {
            throw new RuntimeException('Le dump restauré est vide.');
        }

        $config = config('database.connections.'.self::CONNECTION);
        $optionsFile = dirname($dumpPath).'/client.cnf';

        $lines = ['[client]', 'user='.$config['username'], 'password="'.$config['password'].'"'];

        if (($config['unix_socket'] ?? '') !== '') {
            $lines[] = 'socket='.$config['unix_socket'];
        } else {
            $lines[] = 'host='.$config['host'];
            $lines[] = 'port='.$config['port'];
        }

        file_put_contents($optionsFile, implode("\n", $lines)."\n");
        chmod($optionsFile, 0600);

        try {
            $process = Process::fromShellCommandline(
                sprintf(
                    '%s --defaults-extra-file=%s %s < %s',
                    escapeshellcmd((string) config('ops.mysql_client_path', 'mysql')),
                    escapeshellarg($optionsFile),
                    escapeshellarg($database),
                    escapeshellarg($dumpPath),
                ),
            );
            $process->setTimeout(900);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'Le dump est illisible par le moteur : '.trim($process->getErrorOutput()),
                );
            }
        } finally {
            @unlink($optionsFile);
        }
    }

    /** @return list<string> */
    private function tablesIn(string $database): array
    {
        $rows = DB::connection(self::CONNECTION)->select(
            'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ?',
            [$database],
        );

        return array_map(static fn (object $row): string => (string) $row->name, $rows);
    }

    /**
     * AC 15 — contrôles cumulatifs, limités aux tables réellement présentes.
     *
     * @param  list<string>  $tables
     * @return list<array{name: string, rows: int}>
     */
    private function runCumulativeChecks(string $database, array $tables): array
    {
        $checks = [];

        foreach (self::CUMULATIVE_CHECKS as $table => $label) {
            if (! in_array($table, $tables, true)) {
                continue;
            }

            $count = DB::connection(self::CONNECTION)->selectOne(
                "SELECT COUNT(*) AS total FROM `{$database}`.`{$table}`",
            );
            $checks[] = ['name' => $label, 'rows' => (int) ($count->total ?? 0)];
        }

        if ($checks === []) {
            throw new RuntimeException(
                "La base restaurée ne contient aucune table attendue : l'archive ne correspond pas à cette application.",
            );
        }

        // Le journal d'audit vide est **suspect, mais pas toujours anormal**.
        //
        // Sur une base en service, il signale une restauration partielle : toute écriture sensible
        // produit une entrée d'audit, donc des données sans audit sont des données arrivées par un
        // autre chemin que l'application.
        //
        // Sur une installation neuve, il est simplement vide, comme tout le reste. Distinguer les
        // deux cas évite un faux échec au premier jour d'exploitation — et un faux échec appris
        // par cœur est un vrai échec qu'on ne verra plus.
        $auditRows = 0;
        $otherRows = 0;

        foreach ($checks as $check) {
            if ($check['name'] === "le journal d'audit") {
                $auditRows = $check['rows'];

                continue;
            }

            $otherRows += $check['rows'];
        }

        if ($auditRows === 0 && $otherRows > 0) {
            throw new RuntimeException(sprintf(
                "La base restaurée porte %d ligne(s) métier sans aucune entrée d'audit : restauration partielle.",
                $otherRows,
            ));
        }

        return $checks;
    }

    /**
     * Garde-fou : seul un nom au préfixe attendu peut être créé ou détruit. Sans lui, une variable
     * mal formée pourrait viser la base de production.
     */
    private function assertSafeScratchName(string $name): void
    {
        if (preg_match('/\A'.preg_quote(self::SCRATCH_PREFIX, '/').'[0-9_]+\z/', $name) !== 1) {
            throw new RuntimeException("Nom de base jetable refusé : « {$name} ».");
        }
    }
}
