<?php

namespace Tests\Feature\Http;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\DatabaseManager;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    /**
     * AC 4 de la story 1.1 — `/up` est public, complet et **assaini**.
     *
     * L'assertion d'origine interdisait toute occurrence du mot « backup » : à ce jalon, la
     * sauvegarde n'existait pas et sa seule mention aurait été une fuite. La story 11.1 (AC 10)
     * exige désormais que `/up` expose l'**âge** de la dernière sauvegarde, pour qu'une
     * surveillance externe puisse alerter.
     *
     * Le critère d'origine n'est pas affaibli, il est rendu explicite : ce qui était interdit
     * n'était pas le mot, c'était la **fuite**. Le test vérifie donc maintenant qu'aucun chemin,
     * fournisseur, bucket ni phrase secrète n'apparaît — la liste ci-dessous est plus stricte que
     * l'interdiction d'un seul mot.
     */
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
                    // Story 11.1 AC 10 : l'âge de sauvegarde fait désormais partie du contrat.
                    'backup' => ['status', 'state', 'age_hours', 'max_age_hours'],
                ],
                'timestamp',
            ]);

        $payload = (string) $response->getContent();
        $this->assertStringNotContainsString(base_path(), $payload);

        // Aucune indication permettant de trouver ou d'ouvrir une archive, ni aucun secret.
        foreach ([
            'password', 'passphrase', 'secret', 'bucket', 'endpoint',
            'access_key', 'storage/app', '.env',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                strtolower($payload),
                "`/up` est public : « {$forbidden} » ne doit jamais y apparaître.",
            );
        }
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
