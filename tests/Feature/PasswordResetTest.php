<?php

namespace Tests\Feature;

use App\Exceptions\Identity\PasswordResetVerificationFailed;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\LoginAttemptService;
use App\Services\Identity\PasswordResetService;
use App\Services\Identity\PasswordResetVerificationService;
use App\Support\Auditing\AuditLogger;
use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class PasswordResetTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.key' => 'evolution-test-key',
            'services.evolution.instance' => 'ptr-test',
        ]);
        $this->fakeEvolution();
    }

    public function test_ac_2_reset_uses_one_time_code_sets_temporary_password_and_forces_change(): void
    {
        $actor = User::factory()->active()->create();
        $target = User::factory()->active()->create(['password' => 'Ancien-Secret']);
        $originalHash = $target->password;
        $code = $this->initiateAndReadCode($actor, $target);

        $result = app(PasswordResetService::class)->reset($actor, $target, $code);
        $freshTarget = $target->fresh();

        $this->assertNotSame($originalHash, $freshTarget->password);
        $this->assertTrue(Hash::check($result['temporary_password'], $freshTarget->password));
        $this->assertTrue($freshTarget->must_change_password);
        $this->assertSame(32, strlen($result['temporary_password']));

        $this->expectException(PasswordResetVerificationFailed::class);
        app(PasswordResetService::class)->reset($actor, $target, $code);
    }

    public function test_ac_3_reset_revokes_two_target_sessions_but_preserves_actor_session(): void
    {
        $this->useDatabaseSessions();
        $actorPassword = 'Direction-Session-2.8';
        $targetPassword = 'Cible-Session-2.8';
        $actor = User::factory()->active()->create(['password' => $actorPassword]);
        $target = User::factory()->active()->create(['password' => $targetPassword]);
        $actorSession = $this->openSession($actor, $actorPassword);
        $firstTargetSession = $this->openSession($target, $targetPassword);
        $secondTargetSession = $this->openSession($target, $targetPassword);
        $code = $this->initiateAndReadCode($actor, $target);

        app(PasswordResetService::class)->reset($actor, $target, $code);

        $this->assertDatabaseHas('sessions', ['id' => $actorSession, 'user_id' => $actor->getKey()]);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->getKey())->count());
        $this->assertSessionIsAccepted($actorSession);
        $this->assertSessionIsRejected($firstTargetSession);
        $this->assertSessionIsRejected($secondTargetSession);
    }

    public function test_ac_4_audit_names_actor_and_target_without_password_material(): void
    {
        $actor = User::factory()->active()->create();
        $target = User::factory()->active()->create([
            'failed_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);
        $code = $this->initiateAndReadCode($actor, $target);
        $result = app(PasswordResetService::class)->reset($actor, $target, $code);

        $resetAudit = AuditLog::query()->where('action', 'password_reset_by_administrator')->sole();
        $unlockAudit = AuditLog::query()->where('action', 'login_lock_cleared_by_password_reset')->sole();
        $actorLabel = $actor->person->full_name;
        $targetLabel = $target->person->full_name;

        $this->assertSame($actor->getKey(), $resetAudit->actor_id);
        $this->assertSame($actorLabel, $resetAudit->actor_label);
        $this->assertSame($target->getKey(), $resetAudit->auditable_id);
        $this->assertStringContainsString($actorLabel, (string) $resetAudit->reason);
        $this->assertStringContainsString($targetLabel, (string) $resetAudit->reason);
        $this->assertSame($actor->getKey(), $unlockAudit->actor_id);
        $this->assertSame($targetLabel, $unlockAudit->new_values['target_label']);
        $this->assertSame('Code WhatsApp confirmé sur le numéro enregistré.', $resetAudit->new_values['identity_verification']);

        $serializedValues = json_encode([
            $resetAudit->old_values,
            $resetAudit->new_values,
            $unlockAudit->old_values,
            $unlockAudit->new_values,
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsStringIgnoringCase('password', $serializedValues);
        $this->assertStringNotContainsString($result['temporary_password'], $serializedValues);
        $this->assertStringNotContainsString($target->password, $serializedValues);
    }

    public function test_ac_4_audit_failure_rolls_back_password_unlock_and_session_revocation(): void
    {
        $this->useDatabaseSessions();
        $actor = User::factory()->active()->create();
        $target = User::factory()->active()->create([
            'password' => 'Secret-Avant-Echec',
            'failed_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);
        $originalHash = $target->password;
        $sessionId = $this->insertSession($target);
        $code = $this->initiateAndReadCode($actor, $target);
        $calls = 0;
        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('record')->zeroOrMoreTimes()->andReturn(new AuditLog);
        $auditLogger->shouldReceive('runExplicitly')
            ->twice()
            ->andReturnUsing(function (mixed $auditable, Closure $operation) use (&$calls): mixed {
                $calls++;
                $result = $operation();

                if ($calls === 2) {
                    throw new RuntimeException('Échec audit simulé');
                }

                return $result;
            });
        $this->app->instance(AuditLogger::class, $auditLogger);

        try {
            app(PasswordResetService::class)->reset($actor, $target, $code);
            $this->fail("L'échec d'audit devait interrompre la réinitialisation.");
        } catch (RuntimeException $exception) {
            $this->assertSame('Échec audit simulé', $exception->getMessage());
        }

        $freshTarget = $target->fresh();
        $this->assertSame($originalHash, $freshTarget->password);
        $this->assertSame(5, $freshTarget->failed_attempts);
        $this->assertNotNull($freshTarget->locked_until);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $target->getKey()]);
    }

    public function test_ac_2_blocked_account_can_login_with_temporary_password_after_reset(): void
    {
        config([
            'login-security.max_failed_attempts' => 2,
            'login-security.rate_limit_attempts' => 99,
        ]);
        $actor = User::factory()->active()->create();
        $target = User::factory()->active()->create(['password' => 'Secret-Oublie']);
        $loginAttempts = app(LoginAttemptService::class);
        $loginAttempts->attempt($target->phone, 'Erreur-1', '192.0.2.10', 'Test 2.8');
        $loginAttempts->attempt($target->phone, 'Erreur-2', '192.0.2.11', 'Test 2.8');
        $this->assertNotNull($target->fresh()->locked_until);

        $code = $this->initiateAndReadCode($actor, $target);
        $result = app(PasswordResetService::class)->reset($actor, $target, $code);

        $target->refresh();
        $this->assertSame(0, $target->failed_attempts);
        $this->assertNull($target->locked_until);
        $this->post(route('login.store'), [
            'phone' => $target->phone,
            'password' => $result['temporary_password'],
        ])->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticatedAs($target);
        $this->get(route('home'))->assertRedirect(route('password.change.edit'));
    }

    private function fakeEvolution(): void
    {
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            'https://evolution.test/message/sendText/*' => Http::response(['key' => ['id' => 'message-id']], 201),
        ]);
    }

    private function initiateAndReadCode(User $actor, User $target): string
    {
        app(PasswordResetVerificationService::class)->initiate($actor, $target);
        $request = collect(Http::recorded())
            ->map(static fn (array $record): Request => $record[0])
            ->last(static fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
        $this->assertInstanceOf(Request::class, $request);
        preg_match('/\b([0-9]{6})\b/', (string) $request['text'], $matches);
        $code = $matches[1] ?? '';
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);

        return $code;
    }

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database', 'session.connection' => config('database.default')]);
        app('session')->forgetDrivers();
    }

    private function openSession(User $user, string $password): string
    {
        $existing = DB::table('sessions')->where('user_id', $user->getKey())->pluck('id')->all();
        $this->resetHttpState();
        $this->withCookie($this->sessionCookieName(), Str::random(40))->post(route('login.store'), [
            'phone' => $user->phone,
            'password' => $password,
        ])->assertRedirect(route('home', absolute: false));
        $sessionId = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when($existing !== [], fn ($query) => $query->whereNotIn('id', $existing))
            ->pluck('id')
            ->sole();
        $this->assertIsString($sessionId);

        return $sessionId;
    }

    private function insertSession(User $user): string
    {
        $sessionId = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->getKey(),
            'ip_address' => '192.0.2.20',
            'user_agent' => 'Test 2.8',
            'payload' => base64_encode(''),
            'last_activity' => now()->getTimestamp(),
        ]);

        return $sessionId;
    }

    private function assertSessionIsAccepted(string $sessionId): void
    {
        $this->resetHttpState();
        $this->withCookie($this->sessionCookieName(), $sessionId)->get(route('home'))->assertOk();
    }

    private function assertSessionIsRejected(string $sessionId): void
    {
        $this->resetHttpState();
        $this->withCookie($this->sessionCookieName(), $sessionId)->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function resetHttpState(): void
    {
        app('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
        Auth::forgetGuards();
        $this->app->forgetInstance('auth.driver');
    }

    private function sessionCookieName(): string
    {
        return (string) config('session.cookie');
    }
}
