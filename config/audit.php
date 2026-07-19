<?php

return [
    'database' => [
        'migration_connection' => env('AUDIT_DB_MIGRATION_CONNECTION'),
        'app_username' => env('AUDIT_DB_APP_USERNAME'),
        'app_host' => env('AUDIT_DB_APP_HOST', '%'),
    ],
];
