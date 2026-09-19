<?php

/**
 * Réglages d'exploitation (story 11.1).
 *
 * Ces valeurs ne sont pas du métier : elles décrivent où l'application écrit ses traces
 * d'exploitation et à partir de quel seuil elle alerte. Elles vivent ici pour rester lisibles par
 * l'exploitant sans lire le code.
 */
return [

    /**
     * Registre persistant des tests de restauration (AC 16).
     *
     * En production, il pointe vers `shared/ops/restore-log.md` : **hors de toute release**, car
     * une release est supprimée à la rotation et emporterait le registre avec elle.
     */
    'restore_log_path' => env('OPS_RESTORE_LOG_PATH'),

    /**
     * Seuil de requête lente (AC 23). 500 ms est le seuil de l'architecture § 22.4 : au-delà, sur
     * une connexion à 400 kbit/s, la requête n'est plus le facteur limitant mais elle le devient.
     */
    'slow_query_threshold_ms' => (int) env('OPS_SLOW_QUERY_THRESHOLD_MS', 500),

    /**
     * Destinataire des alertes d'exploitation. Ce canal est **réservé à l'exploitation** : il ne
     * transporte jamais de notification métier (AC 20).
     */
    'alert_mail_to' => env('OPS_ALERT_MAIL_TO'),

    'alert_webhook_url' => env('OPS_ALERT_WEBHOOK_URL'),

    /** Contact d'astreinte, affiché dans les runbooks. Jamais un numéro en dur dans le code. */
    'on_call_contact' => env('OPS_ON_CALL_CONTACT'),

    /**
     * Chemin du client `mysql`, utilisé par `ptr:test-restore` pour importer un dump.
     *
     * Le client est indispensable : un dump porteur de déclencheurs contient des directives
     * `DELIMITER` que PDO ne sait pas exécuter.
     */
    'mysql_client_path' => env('OPS_MYSQL_CLIENT_PATH', 'mysql'),
];
