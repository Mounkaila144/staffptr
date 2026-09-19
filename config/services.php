<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'evolution' => [
        'url' => env('EVOLUTION_API_URL'),
        'key' => env('EVOLUTION_API_KEY'),
        'instance' => env('EVOLUTION_INSTANCE'),
        'timeout_seconds' => 5,

        /**
         * Autorisation d'émission réelle (story 11.1, AC 37).
         *
         * **La cible de cette garde est la préproduction.** Elle contient des données issues de la
         * production, éventuellement mal anonymisées, et ses numéros peuvent donc être réels. Une
         * émission depuis la préproduction atteindrait de vraies personnes.
         *
         * La garde porte sur la **configuration d'environnement**, jamais sur le contenu de la
         * base. L'ordre compte : si l'on comptait sur des numéros factices, une anonymisation
         * *incomplète* suffirait à envoyer de vrais messages.
         *
         * Trois régimes, et un seul atteint le réseau :
         *
         * | Environnement | Émission | Pourquoi |
         * |---|---|---|
         * | `local`, `testing` | autorisée par la configuration, **jamais réelle** | Le client HTTP est simulé ; aucune requête ne sort. |
         * | `staging`, `preprod` | **refusée** | Données réelles possibles — c'est le cas dangereux. |
         * | `production` | autorisée si `EVOLUTION_ALLOW_REAL_DELIVERY=true` | Deux gestes délibérés. Point de contrôle de DEC-15 : HTTPS et port IP public fermé. |
         */
        'allow_real_delivery' => match (env('APP_ENV')) {
            'local', 'testing' => true,
            'production' => env('EVOLUTION_ALLOW_REAL_DELIVERY', false) === true,
            default => false,
        },
    ],

];
