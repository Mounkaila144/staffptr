<?php

namespace Tests\Feature;

use App\Logging\RedactSensitiveDataProcessor;
use Illuminate\Http\Request;
use Illuminate\Log\Formatters\JsonFormatter;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\PsrLogMessageProcessor;
use RuntimeException;
use Tests\TestCase;

class SensitiveLogRedactionTest extends TestCase
{
    public function test_ac_6_passwords_tokens_authorization_cookies_message_and_trace_are_absent_from_written_log(): void
    {
        $password = 'MotDePasse-ULTRA-secret-2026';
        $token = 'jeton-confidentiel-123';
        $authorization = 'Bearer autorisation-confidentielle-456';
        $cookie = 'session-confidentielle-789';
        $request = Request::create(
            '/journal-test',
            'POST',
            [
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
                'secret' => 'secret-applicatif-321',
            ],
            ['ptrstaff_session' => $cookie],
            [],
            ['HTTP_AUTHORIZATION' => $authorization],
        );
        $route = new Route(['POST'], '/journal-test', fn (): null => null);
        $route->name('testing.log-redaction');
        $request->setRouteResolver(fn (): Route => $route);
        $request->attributes->set('request_id', '12345678-1234-1234-1234-123456789abc');
        $this->app->instance('request', $request);
        $exception = $this->capturePasswordException($password);

        $basePath = sys_get_temp_dir().'/staffptr-security-'.bin2hex(random_bytes(8));
        $logger = Log::build($this->jsonLogConfiguration($basePath));
        $logger->error('Échec avec {password}', [
            'exception' => $exception,
            'password' => $password,
            'password_confirmation' => $password,
            'token' => $token,
            'secret' => 'secret-applicatif-321',
            'headers' => [
                'Authorization' => $authorization,
                'Cookie' => $cookie,
            ],
        ]);

        $files = glob($basePath.'-*.log');

        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $writtenLog = (string) file_get_contents($files[0]);

        try {
            foreach ([$password, $token, $authorization, $cookie, 'secret-applicatif-321'] as $secret) {
                $this->assertStringNotContainsString($secret, $writtenLog);
            }

            $record = json_decode($writtenLog, true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame('[MASQUÉ]', $record['context']['password'] ?? null);
            $this->assertSame('testing.log-redaction', $record['extra']['route'] ?? null);
            $this->assertSame('12345678-1234-1234-1234-123456789abc', $record['extra']['request_id'] ?? null);
            $this->assertIsArray($record['context']['exception']['trace'] ?? null);
        } finally {
            @unlink($files[0]);
        }
    }

    public function test_ac_7_personal_content_and_complete_requests_are_not_written_but_object_ids_remain(): void
    {
        $processor = app(RedactSensitiveDataProcessor::class);
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'Objet mis à jour',
            context: [
                'person_id' => 42,
                'person' => ['name' => 'Personne Exemple', 'phone' => '+22790000000'],
                'request' => ['complete' => 'contenu privé'],
            ],
        );
        $processed = $processor($record);

        $this->assertSame(42, $processed->context['person_id']);
        $this->assertSame('[NON JOURNALISÉ]', $processed->context['person']);
        $this->assertSame('[NON JOURNALISÉ]', $processed->context['request']);
    }

    private function capturePasswordException(string $password): RuntimeException
    {
        try {
            $this->throwPasswordException($password);
        } catch (RuntimeException $exception) {
            return $exception;
        }

        throw new RuntimeException('La fixture devait lever une exception.');
    }

    private function throwPasswordException(string $password): never
    {
        throw new RuntimeException("Échec contrôlé avec le mot de passe {$password}");
    }

    /** @return array<string, mixed> */
    private function jsonLogConfiguration(string $basePath): array
    {
        return [
            'driver' => 'monolog',
            'handler' => RotatingFileHandler::class,
            'level' => 'debug',
            'handler_with' => [
                'filename' => $basePath.'.log',
                'maxFiles' => 30,
            ],
            'formatter' => JsonFormatter::class,
            'formatter_with' => [
                'includeStacktraces' => true,
            ],
            'processors' => [
                PsrLogMessageProcessor::class,
                RedactSensitiveDataProcessor::class,
            ],
        ];
    }
}
