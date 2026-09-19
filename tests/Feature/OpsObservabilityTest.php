<?php

namespace Tests\Feature;

use App\Logging\RedactSensitiveDataProcessor;
use App\Services\Platform\WhatsAppChannel;
use Illuminate\Log\Formatters\JsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Tests\TestCase;

/**
 * Story 11.1, Task 5 — supervision et protection des journaux (AC 23, AC 25, AC 26).
 *
 * L'AC 26 est catégorique : « Aucun secret, jeton ni donnée personnelle n'apparaît dans les
 * journaux ou alertes ; des tests le prouvent. » Ces tests sont cette preuve.
 */
class OpsObservabilityTest extends TestCase
{
    /** AC 23 — la surveillance des requêtes lentes est branchée au seuil de 500 ms. */
    public function test_ac_23_slow_queries_are_watched_at_the_architecture_threshold(): void
    {
        $this->assertSame(500, (int) config('ops.slow_query_threshold_ms'));

        $source = (string) file_get_contents(app_path('Providers/ObservabilityServiceProvider.php'));
        $this->assertStringContainsString('whenQueryingForLongerThan', $source);
    }

    /**
     * AC 23, AC 26 — **le point sensible** : une requête lente est journalisée avec son SQL, mais
     * **jamais avec ses valeurs liées**. Une requête paramétrée contient des numéros de téléphone
     * et des montants ; une lenteur n'est pas une raison de les recopier dans un fichier conservé
     * 30 jours.
     */
    public function test_ac_26_slow_query_logging_never_records_bound_values(): void
    {
        $source = (string) file_get_contents(app_path('Providers/ObservabilityServiceProvider.php'));

        $this->assertStringNotContainsString("'bindings'", $source);
        $this->assertStringNotContainsString('getRawQueryLog', $source);
        $this->assertStringContainsString('slowest_sql', $source);
    }

    /** AC 26 — le processeur de masquage est branché sur le canal technique. */
    public function test_ac_26_the_redaction_processor_is_wired_on_the_technical_channel(): void
    {
        $processors = config('logging.channels.daily.processors');

        $this->assertContains(RedactSensitiveDataProcessor::class, $processors);
    }

    /** AC 25 — canal JSON quotidien conservé 30 jours. */
    public function test_ac_25_technical_logs_are_daily_json_kept_for_thirty_days(): void
    {
        // Le canal utilise un `RotatingFileHandler` explicite : la rétention se lit dans
        // `handler_with.maxFiles`, pas dans la clé `days` du pilote `single` de Laravel.
        $this->assertSame(30, (int) config('logging.channels.daily.handler_with.maxFiles'));
        $this->assertSame(JsonFormatter::class, config('logging.channels.daily.formatter'));

        // Le canal par défaut est `daily` en production. En test, l'environnement peut le
        // remplacer par `stack` : c'est la valeur du fichier de configuration qui fait foi, pas
        // celle qu'un `.env` de test surcharge.
        $source = (string) file_get_contents(config_path('logging.php'));
        $this->assertStringContainsString("env('LOG_CHANNEL', 'daily')", $source);
    }

    /**
     * AC 26 — la preuve directe : un enregistrement contenant secrets et données personnelles
     * ressort masqué.
     */
    public function test_ac_26_secrets_and_personal_data_are_masked_before_reaching_the_log(): void
    {
        $processor = new RedactSensitiveDataProcessor;

        $record = $processor(new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'daily',
            level: Level::Warning,
            message: 'Échec de sauvegarde',
            context: [
                'password' => 'phrase-secrete-archive',
                'token' => 'jeton-evolution-api',
                'authorization' => 'Bearer abcdef',
                'phone' => '+22790112233',
                'name' => 'Amina Zakari',
            ],
        ));

        $serialized = json_encode($record->context, JSON_THROW_ON_ERROR);

        foreach ([
            'phrase-secrete-archive',
            'jeton-evolution-api',
            'Bearer abcdef',
            '+22790112233',
            'Amina Zakari',
        ] as $sensitive) {
            $this->assertStringNotContainsString(
                $sensitive,
                $serialized,
                "La valeur « {$sensitive} » ne doit jamais atteindre le journal.",
            );
        }
    }

    /**
     * AC 26 — aucune configuration d'exploitation ne contient de secret littéral. Les valeurs
     * viennent toutes de l'environnement.
     */
    public function test_ac_26_no_operational_config_contains_a_literal_secret(): void
    {
        foreach (['backup.php', 'ops.php', 'services.php', 'filesystems.php'] as $file) {
            $source = (string) file_get_contents(config_path($file));

            // Toute clé sensible doit être lue depuis l'environnement, jamais écrite en dur.
            foreach (['passphrase', 'secret_access_key', 'access_key_id'] as $needle) {
                if (! str_contains(strtolower($source), $needle)) {
                    continue;
                }

                $this->assertMatchesRegularExpression(
                    '/env\([^)]*'.preg_quote(strtoupper($needle), '/').'/i',
                    $source,
                    "Dans {$file}, « {$needle} » doit provenir de l'environnement.",
                );
            }
        }
    }

    /** AC 20 — le canal d'alerte d'exploitation est distinct des notifications métier. */
    public function test_ac_20_the_operations_alert_channel_is_separate_from_business_notifications(): void
    {
        $backupChannels = config('backup.notifications.notifications');

        foreach ($backupChannels as $channels) {
            $this->assertNotContains(
                WhatsAppChannel::class,
                $channels,
                "Une alerte d'exploitation ne passe jamais par le canal métier WhatsApp.",
            );
        }
    }

    /** AC 8 — le succès quotidien n'est pas notifié : une alerte quotidienne cesse d'être lue. */
    public function test_daily_success_is_not_notified_so_that_absence_stands_out(): void
    {
        $notifications = config('backup.notifications.notifications');

        $this->assertSame(
            [],
            $notifications[BackupWasSuccessfulNotification::class],
        );
    }
}
