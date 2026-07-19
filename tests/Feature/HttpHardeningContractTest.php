<?php

namespace Tests\Feature;

use App\Logging\RedactSensitiveDataProcessor;
use Illuminate\Log\Formatters\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Tests\TestCase;

class HttpHardeningContractTest extends TestCase
{
    public function test_ac_3_session_cookie_contract_is_hardened_without_breaking_local_http(): void
    {
        $example = $this->readFile('.env.example');
        $environments = $this->readFile('docs/ops/environments.md');

        foreach ([
            'SESSION_LIFETIME=480',
            'SESSION_ENCRYPT=true',
            'SESSION_HTTP_ONLY=true',
            'SESSION_SAME_SITE=lax',
        ] as $setting) {
            $this->assertStringContainsString($setting, $example);
            $this->assertGreaterThanOrEqual(2, substr_count($environments, $setting));
        }

        $this->assertStringContainsString('SESSION_SECURE_COOKIE=false', $example);
        $this->assertGreaterThanOrEqual(2, substr_count($environments, 'SESSION_SECURE_COOKIE=true'));
        $this->assertSame(480, config('session.lifetime'));
        $this->assertTrue(config('session.encrypt'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_ac_6_daily_log_contract_is_json_redacted_and_retained_for_thirty_days(): void
    {
        $channel = config('logging.channels.daily');

        $this->assertSame('monolog', $channel['driver'] ?? null);
        $this->assertSame(RotatingFileHandler::class, $channel['handler'] ?? null);
        $this->assertSame(30, $channel['handler_with']['maxFiles'] ?? null);
        $this->assertSame(JsonFormatter::class, $channel['formatter'] ?? null);
        $this->assertTrue($channel['formatter_with']['includeStacktraces'] ?? false);
        $this->assertContains(RedactSensitiveDataProcessor::class, $channel['processors'] ?? []);
    }

    public function test_ac_8_production_templates_never_enable_debug_mode(): void
    {
        $environments = $this->readFile('docs/ops/environments.md');

        $this->assertGreaterThanOrEqual(2, substr_count($environments, 'APP_DEBUG=false'));
        $this->assertDoesNotMatchRegularExpression('/(?:staging|production)[\s\S]{0,500}APP_DEBUG=true/i', $environments);
    }

    public function test_ac_1_3_and_8_server_hardening_has_an_executable_operator_runbook(): void
    {
        $runbook = $this->readFile('docs/ops/http-hardening.md');

        foreach ([
            'certbot --apache',
            'systemctl status certbot.timer',
            'curl -I http://staff.ptrniger.com',
            'HTTP/1.1 301',
            'SSLProtocol -all +TLSv1.2 +TLSv1.3',
            'ufw allow 22/tcp',
            'ufw allow 80/tcp',
            'ufw allow 443/tcp',
            '127.0.0.1:3306',
            '127.0.0.1:6379',
            'APP_DEBUG=false',
            'ptr:check-invariants',
            'contenu mixte',
            'preload',
        ] as $contract) {
            $this->assertStringContainsString($contract, $runbook);
        }
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertNotFalse($contents);

        return $contents;
    }
}
