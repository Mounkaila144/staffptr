<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
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
    ) {}

    public function __invoke(): JsonResponse
    {
        $database = $this->databaseStatus();
        $cache = $this->cacheStatus();
        $disk = $this->diskStatus();

        $status = match (true) {
            $database['status'] === 'failed' => 'failed',
            $cache['status'] !== 'ok', $disk['status'] !== 'ok' => 'degraded',
            default => 'ok',
        };

        return response()->json([
            'status' => $status,
            'version' => (string) config('app.version'),
            'checks' => [
                'database' => $database,
                'cache' => $cache,
                'disk' => $disk,
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
