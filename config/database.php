<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],

            /**
             * Sauvegarde — story 11.1 AC 2.
             *
             * `--single-transaction` prend le dump dans une transaction cohérente : sans lui, un
             * dump démarré pendant un encaissement pourrait contenir la ligne de paiement sans son
             * mouvement de compte. L'archive serait restaurable et **fausse**, ce qui est pire
             * qu'une archive absente.
             *
             * `--skip-lock-tables` est le corollaire : verrouiller les tables sur un VPS partagé
             * bloquerait l'application pendant toute la durée du dump.
             */
            'dump' => [
                'dump_binary_path' => env('DB_DUMP_BINARY_PATH', ''),
                'use_single_transaction' => true,
                'add_extra_option' => '--skip-lock-tables --routines --triggers',
                'timeout' => 60 * 15,
            ],
        ],

        /**
         * Connexion réservée à la **sauvegarde** (story 11.1, AC 2).
         *
         * Elle existe pour une raison précise et vérifiée en production : sans le privilège
         * `TRIGGER`, `mysqldump` **omet silencieusement les déclencheurs** — l'archive se restaure
         * sans les barrières d'immuabilité du journal d'audit et du module financier. Le défaut ne
         * se voit qu'au moment de la restauration, c'est-à-dire trop tard.
         *
         * L'utilisateur applicatif ne doit pas porter ces privilèges : il n'a pas à lire les
         * définitions de schéma. Un troisième rôle, **en lecture seule mais complet**, est la
         * bonne réponse. Le moindre privilège ne veut pas dire trop peu de privilèges pour faire
         * le travail — il veut dire exactement ceux qu'il faut.
         *
         * `--events` a été retiré : il exige le privilège `EVENT`, l'application n'utilise aucun
         * événement MariaDB, et le demander élargirait le rôle sans rien apporter.
         */
        'mysql_backup' => [
            'driver' => 'mysql',
            'host' => env('DB_BACKUP_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_BACKUP_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_BACKUP_DATABASE', env('DB_DATABASE', 'laravel')),
            'username' => env('DB_BACKUP_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_BACKUP_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_BACKUP_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'dump' => [
                'dump_binary_path' => env('DB_DUMP_BINARY_PATH', ''),
                'use_single_transaction' => true,
                'add_extra_option' => '--skip-lock-tables --routines --triggers',
                'timeout' => 60 * 15,
            ],
        ],

        /**
         * Connexion réservée au **test de restauration** (story 11.1, AC 14).
         *
         * Créer et détruire une base jetable exige des droits de schéma que ni l'utilisateur
         * applicatif ni celui de sauvegarde ne doivent porter. Les privilèges de ce rôle sont
         * limités au **motif** `ptr_restore_test_%` : il lui est structurellement impossible
         * d'atteindre `staffptr_production`, `staffptr_staging` ou le schéma d'une autre
         * application du VPS partagé.
         */
        'mysql_restore' => [
            'driver' => 'mysql',
            'host' => env('DB_RESTORE_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_RESTORE_PORT', env('DB_PORT', '3306')),
            // Aucune base par défaut : la base jetable est créée puis sélectionnée à l'exécution.
            'database' => env('DB_RESTORE_DATABASE', ''),
            'username' => env('DB_RESTORE_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('DB_RESTORE_PASSWORD', env('DB_PASSWORD', '')),
            'unix_socket' => env('DB_RESTORE_SOCKET', env('DB_SOCKET', '')),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
        ],

        'mysql_migration' => [
            'driver' => 'mysql',
            'url' => env('DB_MIGRATION_URL'),
            'host' => env('DB_MIGRATION_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('DB_MIGRATION_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_MIGRATION_DATABASE', env('DB_DATABASE', 'laravel')),
            'username' => env('DB_MIGRATION_USERNAME', 'root'),
            'password' => env('DB_MIGRATION_PASSWORD', ''),
            'unix_socket' => env('DB_MIGRATION_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
