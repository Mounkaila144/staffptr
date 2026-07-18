<?php

namespace Tests\Feature\Http;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\DatabaseManager;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_ac_4_health_endpoint_is_public_complete_and_sanitized(): void
    {
        $response = $this->getJson(route('health'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.disk.status', 'ok')
            ->assertJsonStructure([
                'status',
                'version',
                'checks' => [
                    'database' => ['status'],
                    'cache' => ['status'],
                    'disk' => ['status', 'free_bytes'],
                ],
                'timestamp',
            ]);

        $payload = $response->getContent();
        $this->assertStringNotContainsString(base_path(), $payload);
        $this->assertStringNotContainsString('password', strtolower($payload));
        $this->assertStringNotContainsString('backup', strtolower($payload));
        $timestamp = $response->json('timestamp');
        $this->assertIsString($timestamp);
        $this->assertStringEndsWith('+01:00', $timestamp);
    }

    public function test_ac_5_database_failure_returns_an_explicit_http_failure(): void
    {
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->once()->andThrow(new RuntimeException('secret database detail'));
        $this->app->instance(DatabaseManager::class, $database);

        $this->getJson(route('health'))
            ->assertServiceUnavailable()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('checks.database.status', 'failed')
            ->assertJsonMissing(['secret database detail']);
    }

    public function test_ac_5_cache_failure_only_degrades_the_response(): void
    {
        $cache = Mockery::mock(CacheManager::class);
        $cache->shouldReceive('store')->once()->andThrow(new RuntimeException('secret cache detail'));
        $this->app->instance(CacheManager::class, $cache);

        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'degraded')
            ->assertJsonMissing(['secret cache detail']);
    }
}
