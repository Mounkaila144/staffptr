<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => true,
        ],

        /**
         * Copie locale des sauvegardes, conservée 48 h (story 11.1, AC 4).
         *
         * Elle vit hors de `storage/app/private` : ce dernier est **inclus dans l'archive**, et
         * y écrire les sauvegardes ferait qu'une archive contienne les précédentes, avec une
         * croissance exponentielle. Le chemin est hors racine web (NFR15).
         */
        'backups' => [
            'driver' => 'local',
            'root' => env('BACKUP_LOCAL_ROOT', storage_path('app/backups')),
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        /**
         * Stockage objet hors site (story 11.1, AC 4, AC 54).
         *
         * Le disque n'est utilisable qu'une fois DEC-06 tranché — fournisseur, pays d'hébergement
         * et compte. Tant que `BACKUP_OBJECT_BUCKET` est vide, `config/backup.php` ne déclare pas
         * cette destination : mieux vaut une absence signalée qu'un échec quotidien qui noie les
         * vraies alertes.
         */
        'backups-offsite' => [
            'driver' => 's3',
            'key' => env('BACKUP_OBJECT_ACCESS_KEY_ID'),
            'secret' => env('BACKUP_OBJECT_SECRET_ACCESS_KEY'),
            'region' => env('BACKUP_OBJECT_REGION', 'auto'),
            'bucket' => env('BACKUP_OBJECT_BUCKET'),
            'endpoint' => env('BACKUP_OBJECT_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('BACKUP_OBJECT_PATH_STYLE', true),
            'throw' => true,
            'report' => true,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
