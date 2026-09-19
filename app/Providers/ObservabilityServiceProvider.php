<?php

namespace App\Providers;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Observabilité d'exploitation (story 11.1, AC 23, AC 26).
 *
 * Ce fournisseur n'ajoute aucun comportement métier : il branche la surveillance des requêtes
 * lentes sur le canal technique.
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->watchSlowQueries();
    }

    /**
     * AC 23 — au-delà du seuil, un enregistrement est écrit dans le canal d'alerte.
     *
     * **Ce qui est journalisé, et ce qui ne l'est pas.** Le SQL est écrit sans ses liaisons : une
     * requête paramétrée contient des identifiants, des numéros de téléphone et des montants, et
     * une requête lente n'est pas une raison de les recopier dans un fichier conservé 30 jours
     * (AC 26). Le nombre de liaisons suffit à reproduire le problème ; leurs valeurs ne sont
     * jamais nécessaires pour comprendre pourquoi une requête est lente.
     *
     * Le seuil est mesuré sur la connexion entière plutôt que requête par requête : c'est le temps
     * cumulé qui rend une page inutilisable sur une connexion faible.
     */
    private function watchSlowQueries(): void
    {
        $threshold = (int) config('ops.slow_query_threshold_ms', 500);

        if ($threshold < 1) {
            return;
        }

        DB::whenQueryingForLongerThan($threshold, function (Connection $connection): void {
            $queries = $connection->getQueryLog();

            Log::channel((string) config('logging.default'))->warning('Requêtes lentes détectées.', [
                'connection' => $connection->getName(),
                'threshold_ms' => (int) config('ops.slow_query_threshold_ms', 500),
                'query_count' => count($queries),
                // Le SQL est conservé, jamais les valeurs liées.
                'slowest_sql' => $this->slowestStatement($queries),
            ]);
        });
    }

    /**
     * @param  list<array{query?: string, time?: float|int}>  $queries
     */
    private function slowestStatement(array $queries): ?string
    {
        $slowest = null;
        $worstTime = -1.0;

        foreach ($queries as $entry) {
            $time = (float) ($entry['time'] ?? 0);

            if ($time > $worstTime) {
                $worstTime = $time;
                $slowest = isset($entry['query']) ? (string) $entry['query'] : null;
            }
        }

        return $slowest;
    }
}
