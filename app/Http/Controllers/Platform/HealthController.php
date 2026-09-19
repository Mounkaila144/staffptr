<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\BackupStatus;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CacheManager $cache,
        private readonly BackupStatus $backupStatus,
    ) {}

    public function __invoke(): JsonResponse
    {
        $database = $this->databaseStatus();
        $cache = $this->cacheStatus();
        $disk = $this->diskStatus();
        $backup = $this->backupStatus();

        // Une sauvegarde périmée dégrade la santé sans rendre l'application indisponible : elle
        // fonctionne, mais elle n'est plus protégée. Seule la base indisponible produit un 503,
        // pour ne pas changer silencieusement le contrat `/up` établi par la story 1.1 (AC 10).
        //
        // L'état de sauvegarde n'entre dans le statut global **qu'en production** : c'est le seul
        // environnement où l'absence d'archive est une anomalie. Un poste de développement ou une
        // préproduction — qui n'est pas sauvegardée par conception — ne sont pas « dégradés »
        // parce qu'ils n'ont rien à sauvegarder. L'information reste exposée dans tous les cas.
        $backupDegrades = $backup['status'] === 'degraded' && app()->environment('production');

        $status = match (true) {
            $database['status'] === 'failed' => 'failed',
            $cache['status'] !== 'ok', $disk['status'] !== 'ok', $backupDegrades => 'degraded',
            default => 'ok',
        };

        return response()->json([
            'status' => $status,
            'version' => (string) config('app.version'),
            'checks' => [
                'database' => $database,
                'cache' => $cache,
                'disk' => $disk,
                'backup' => $backup,
            ],
            'timestamp' => now((string) config('app.display_timezone'))->toIso8601String(),
        ], $database['status'] === 'failed' ? 503 : 200);
    }

    /** @return array{status: 'ok'|'failed'} */
    private function databaseStatus(): array
    {
        try {
            $this->database->connection()->getPdo();

            return ['status' => 'ok'];
        } catch (Throwable) {
            return ['status' => 'failed'];
        }
    }

    /** @return array{status: 'ok'|'degraded'} */
    private function cacheStatus(): array
    {
        try {
            $cache = $this->cache->store();
            $key = 'health-check:'.Str::uuid()->toString();
            $cache->put($key, true, 5);
            $isAvailable = $cache->get($key) === true;
            $cache->forget($key);

            return ['status' => $isAvailable ? 'ok' : 'degraded'];
        } catch (Throwable) {
            return ['status' => 'degraded'];
        }
    }

    /**
     * Âge de la dernière sauvegarde (AC 10).
     *
     * La charge utile ne contient **ni chemin, ni fournisseur, ni bucket, ni secret** : `/up` est
     * une route publique, et un attaquant ne doit rien y apprendre sur l'emplacement des archives.
     * Elle ne dit que ce qu'une surveillance externe a besoin de savoir pour alerter.
     *
     * Un environnement sans disque de sauvegarde configuré — le développement local — n'est pas
     * dégradé pour autant : il n'a rien à sauvegarder.
     *
     * @return array{status: 'ok'|'degraded'|'not_configured', state: string, age_hours: int|null, max_age_hours: int, offsite_configured: bool}
     */
    private function backupStatus(): array
    {
        if (! is_array(config('filesystems.disks.backups'))) {
            return [
                'status' => 'not_configured',
                'state' => 'non configuree',
                'age_hours' => null,
                'max_age_hours' => BackupStatus::MAX_AGE_HOURS,
                'offsite_configured' => false,
            ];
        }

        $payload = $this->backupStatus->toHealthPayload();

        return [
            'status' => $payload['state'] === 'fraiche' ? 'ok' : 'degraded',
            ...$payload,
        ];
    }

    /** @return array{status: 'ok'|'degraded', free_bytes: int|null} */
    private function diskStatus(): array
    {
        $freeBytes = disk_free_space(storage_path());

        return [
            'status' => $freeBytes === false ? 'degraded' : 'ok',
            'free_bytes' => $freeBytes === false ? null : (int) $freeBytes,
        ];
    }
}
