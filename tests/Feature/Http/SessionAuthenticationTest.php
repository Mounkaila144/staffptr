<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use Tests\Support\IdentityTestCase;

class SessionAuthenticationTest extends IdentityTestCase
{
    public function test_ac_6_login_regenerates_and_persists_a_revocable_database_session(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);
        $password = 'Session-Securisee-2026';
        $user = User::factory()->active()->create([
            'phone' => '+22790123456',
            'password' => $password,
        ]);

        $this->withSession(['session_proof' => 'before-login']);
        $oldSessionId = session()->getId();

        $loginResponse = $this->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => $password,
        ]);
        $loginResponse->assertRedirect(route('home', absolute: false));

        $newSessionId = session()->getId();
        $this->assertNotSame($oldSessionId, $newSessionId);
        $this->assertDatabaseHas('sessions', [
            'id' => $newSessionId,
            'user_id' => $user->getKey(),
        ]);

        $sessionCookie = $loginResponse->getCookie((string) config('session.cookie'));
        $this->assertNotNull($sessionCookie);

        $this->withCookie($sessionCookie->getName(), (string) $sessionCookie->getValue())
            ->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', [
            'id' => $newSessionId,
            'user_id' => $user->getKey(),
        ]);
    }

    public function test_ac_6_session_configuration_and_authenticate_session_are_contractualized(): void
    {
        $sessionConfiguration = (string) file_get_contents(config_path('session.php'));
        $environmentDocumentation = (string) file_get_contents(base_path('docs/ops/environments.md'));
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString('use Illuminate\\Session\\Middleware\\AuthenticateSession;', $bootstrap);
        $this->assertMatchesRegularExpression('/web\(append: \[[\s\S]*AuthenticateSession::class/', $bootstrap);
        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", $sessionConfiguration);
        $this->assertStringContainsString("env('SESSION_LIFETIME', 480)", $sessionConfiguration);
        $this->assertStringContainsString("env('SESSION_ENCRYPT', true)", $sessionConfiguration);
        $this->assertStringContainsString("env('SESSION_HTTP_ONLY', true)", $sessionConfiguration);
        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'lax')", $sessionConfiguration);

        foreach ([
            'SESSION_DRIVER=database',
            'SESSION_LIFETIME=480',
            'SESSION_ENCRYPT=true',
            'SESSION_SECURE_COOKIE=true',
            'SESSION_HTTP_ONLY=true',
            'SESSION_SAME_SITE=lax',
        ] as $setting) {
            $this->assertStringContainsString($setting, $environmentDocumentation);
        }
    }

    public function test_ac_6_sessions_user_id_has_the_deferred_foreign_key(): void
    {
        $foreignKeys = $this->migrationSchema()->getForeignKeys('sessions');
        $sessionUserForeignKey = collect($foreignKeys)->first(
            static fn (array $foreignKey): bool => $foreignKey['columns'] === ['user_id'],
        );

        $this->assertIsArray($sessionUserForeignKey);
        $this->assertSame('users', $sessionUserForeignKey['foreign_table']);
        $this->assertSame(['id'], $sessionUserForeignKey['foreign_columns']);
    }
}
