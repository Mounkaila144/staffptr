<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/**
 * Sauvegarde quotidienne chiffrée (story 11.1, AC 1 à 12, architecture § 21).
 *
 * Ce fichier remplace la configuration publiée par le paquet. Il n'en garde que les clés
 * réellement lues, chacune justifiée : une configuration d'exploitation dont on ne sait plus
 * pourquoi une valeur est là finit par être modifiée à tort le jour d'un incident.
 *
 * **Ce qui n'est jamais sauvegardé ici** (AC 7, AC 8, architecture § 21.4) :
 * `.env`, Redis, `storage/logs`, `node_modules`, `vendor`. Le fichier `.env` fait l'objet d'une
 * sauvegarde manuelle séparée, décrite dans `docs/ops/backup-and-restore.md` : le mêler à
 * l'archive de données reviendrait à ranger la clé dans le coffre.
 */
return [

    'backup' => [

        'name' => env('BACKUP_ARCHIVE_NAME', 'staffptr'),

        'source' => [

            'files' => [

                /**
                 * AC 2 — seules les pièces jointes privées sont archivées. Le code source vit
                 * dans Git : l'inclure gonflerait l'archive sans rien apporter à une restauration.
                 */
                'include' => [
                    storage_path('app/private'),
                ],

                /**
                 * AC 8 — exclusions explicites. `storage/logs` en fait partie : les journaux
                 * techniques ont leur propre rétention de 30 jours et peuvent contenir des
                 * fragments de contexte qui n'ont rien à faire dans une archive conservée des
                 * années.
                 */
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('logs'),
                    storage_path('framework'),
                    storage_path('app/backup-temp'),
                ],

                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            /**
             * AC 2 — le dump est produit avec `--single-transaction`, configuré sur la connexion
             * dans `config/database.php`. Sans lui, un dump pris pendant une écriture financière
             * pourrait contenir une transaction à moitié appliquée : l'archive serait restaurable
             * mais fausse, ce qui est pire qu'une archive absente.
             */
            'databases' => [
                // Connexion **dédiée à la sauvegarde**, en lecture seule mais dotée du privilège
                // `TRIGGER`. Sans lui, `mysqldump` omet silencieusement les déclencheurs
                // d'immuabilité : l'archive se restaurerait sans ses barrières.
                env('DB_BACKUP_CONNECTION', 'mysql_backup'),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [

            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',

            /**
             * AC 4 — deux destinations : une copie locale conservée 48 h pour une restauration
             * rapide, et le stockage objet hors site qui protège d'une perte du serveur.
             *
             * Le disque hors site n'est ajouté **que s'il est configuré** : tant que DEC-06 n'a
             * pas tranché le fournisseur, le déclarer produirait un échec quotidien qui masquerait
             * les vraies alertes. Son absence est signalée par ailleurs — voir
             * `BackupDestinationsInvariant`.
             */
            'disks' => in_array(env('BACKUP_OBJECT_BUCKET'), [null, ''], true)
                ? ['backups']
                : ['backups', 'backups-offsite'],

            /**
             * AC 4, AC 54 — un échec d'envoi hors site ne doit pas empêcher la copie locale, mais
             * il doit rester visible. `continue_on_failure` garde la copie locale ; l'échec est
             * remonté par `backup:monitor` et par l'invariant de destinations.
             */
            'continue_on_failure' => true,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        /**
         * AC 3 — l'archive est chiffrée par mot de passe. **La phrase secrète n'est jamais dans
         * le dépôt** : elle vient de l'environnement, et sa copie de référence est conservée hors
         * du serveur (architecture § 25.5). Une archive chiffrée dont la clé vit sur la machine
         * sauvegardée ne protège de rien.
         */
        'password' => env('BACKUP_ARCHIVE_PASSPHRASE'),
        'encryption' => 'default',

        /**
         * L'archive est relue après écriture. Le coût est réel sur un VPS partagé ; il est accepté
         * parce qu'une archive corrompue découverte le jour de l'incident ne vaut rien.
         */
        'verify_backup' => true,

        'tries' => 2,
        'retry_delay' => 300,
    ],

    /**
     * AC 22, AC 26 — les notifications de sauvegarde sont des **alertes d'exploitation**, pas des
     * notifications applicatives. Elles passent par le canal dédié, jamais par WhatsApp, et ne
     * contiennent aucune donnée métier.
     *
     * Le succès quotidien n'est volontairement pas notifié : une alerte qui arrive tous les jours
     * cesse d'être lue, et c'est l'absence de sauvegarde qui doit se remarquer.
     */
    'notifications' => [

        /**
         * Le canal n'est déclaré que s'il est réellement configuré. Un canal `mail` sans
         * destinataire fait échouer le paquet au démarrage ; le déclarer « au cas où » rendrait la
         * sauvegarde inopérante sur tout environnement où l'adresse n'est pas renseignée — c'est
         * exactement le genre de configuration qui casse le jour du déploiement.
         *
         * En l'absence d'adresse, l'échec part dans le canal technique, où la supervision de
         * `docs/ops/monitoring.md` le récupère. Une alerte dans le journal vaut mieux qu'une
         * alerte qui n'existe pas.
         */
        'notifications' => (static function (): array {
            $channels = env('OPS_ALERT_MAIL_TO') === null ? [] : ['mail'];

            return [
                BackupHasFailedNotification::class => $channels,
                UnhealthyBackupWasFoundNotification::class => $channels,
                CleanupHasFailedNotification::class => $channels,
                // Le succès quotidien n'est pas notifié : une alerte reçue tous les jours cesse
                // d'être lue, et c'est l'absence de sauvegarde qui doit se remarquer.
                BackupWasSuccessfulNotification::class => [],
                HealthyBackupWasFoundNotification::class => [],
                CleanupWasSuccessfulNotification::class => [],
            ];
        })(),

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('OPS_ALERT_MAIL_TO', 'ops@example.invalid'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'ops@example.invalid'),
                'name' => env('MAIL_FROM_NAME', 'PTR Staff — exploitation'),
            ],
        ],

        'slack' => ['webhook_url' => '', 'channel' => null, 'username' => null, 'icon' => null],
        'discord' => ['webhook_url' => '', 'username' => '', 'avatar_url' => ''],
        'webhook' => ['url' => env('OPS_ALERT_WEBHOOK_URL', '')],
    ],

    /** Canal technique JSON quotidien, conservé 30 jours (story 1.6, AC 25). */
    'log_channel' => env('BACKUP_LOG_CHANNEL', 'daily'),

    /**
     * AC 6, AC 22 — `backup:monitor` alerte quand la dernière sauvegarde est trop ancienne.
     *
     * Le seuil est **1 jour**, pas 2 : la sauvegarde est quotidienne, donc une archive de plus
     * d'un jour signifie déjà qu'une exécution a été manquée. Le RPO de 24 h (AC 9) ne laisse pas
     * de marge au-delà.
     */
    'monitor_backups' => [
        [
            'name' => env('BACKUP_ARCHIVE_NAME', 'staffptr'),
            'disks' => in_array(env('BACKUP_OBJECT_BUCKET'), [null, ''], true)
                ? ['backups']
                : ['backups', 'backups-offsite'],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                // Garde-fou de volume : le VPS partage 72 Go avec d'autres applications.
                MaximumStorageInMegabytes::class => (int) env('BACKUP_MAX_STORAGE_MB', 20000),
            ],
        ],
    ],

    /**
     * AC 5 — rotation 7 quotidiennes / 4 hebdomadaires / 12 mensuelles.
     *
     * La conservation métier de dix ans (données du personnel, justificatifs financiers) **n'est
     * pas traitée ici** : elle relève de DEC-11, non tranchée, et son coût disque doit être chiffré
     * avant d'être configuré. La régler par une rétention infinie sur le disque du VPS serait le
     * moyen le plus sûr de saturer un serveur partagé.
     */
    'cleanup' => [

        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 12,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_MAX_STORAGE_MB', 20000),
        ],

        'tries' => 1,
        'retry_delay' => 0,
    ],
];
