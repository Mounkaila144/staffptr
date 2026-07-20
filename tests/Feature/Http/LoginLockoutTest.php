<?php

namespace Tests\Feature\Http;

use App\Enums\UserState;
use App\Models\Identity\LoginAttempt;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use Illuminate\Testing\TestResponse;
use Tests\Support\IdentityTestCase;

class LoginLockoutTest extends IdentityTestCase
{
    public function test_ac_1_account_is_persistently_locked_after_the_configured_failures(): void
    {
        $this->freezeTime();
        config()->set('login-security.max_failed_attempts', 3);
        config()->set('login-security.rate_limit_attempts', 10);
        $user = User::factory()->active()->create([
            'phone' => '+22790123456',
            'password' => 'MotDePasse-Correct-2.6',
        ]);
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->login($user->phone, 'MotDePasse-Errone-2.6')
                ->assertSessionHasErrors(['phone' => 'Numéro ou mot de passe incorrect.']);
        }

        $this->login($user->phone, 'MotDePasse-Errone-2.6')
            ->assertSessionHasErrors(['phone' => $this->blockedMessage(15)]);

        $user->refresh();
        $this->assertSame(3, $user->failed_attempts);
        $this->assertSame(UserState::Actif, $user->state);
        $this->assertSame(now('UTC')->addMinutes(15)->timestamp, $user->locked_until?->timestamp);
        $this->assertSame(3, LoginAttempt::query()->whereBelongsTo($user)->count());
        $this->assertSame(1, AuditLog::query()
            ->where('id', '>', $lastAuditId)
            ->where('action', 'login_lock_started')
            ->count());
    }

    public function test_ac_2_block_message_interpolates_the_configured_duration(): void
    {
        $this->freezeTime();
        config()->set('login-security.max_failed_attempts', 1);
        config()->set('login-security.lockout_minutes', 7);
        config()->set('login-security.rate_limit_attempts', 10);
        $user = User::factory()->active()->create(['password' => 'MotDePasse-Correct-2.6']);

        $this->login($user->phone, 'MotDePasse-Errone-2.6')
            ->assertSessionHasErrors(['phone' => $this->blockedMessage(7)]);

        $this->assertSame(now('UTC')->addMinutes(7)->timestamp, $user->fresh()->locked_until?->timestamp);
    }

    public function test_ac_3_address_limit_blocks_account_b_after_failures_against_account_a(): void
    {
        config()->set('login-security.max_failed_attempts', 99);
        config()->set('login-security.rate_limit_attempts', 3);
        $accountA = User::factory()->active()->create(['password' => 'Correct-A-2.6']);
        $accountB = User::factory()->active()->create(['password' => 'Correct-B-2.6']);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->login($accountA->phone, 'Errone-2.6', '198.51.100.10')
                ->assertSessionHasErrors(['phone' => 'Numéro ou mot de passe incorrect.']);
        }

        $this->login($accountB->phone, 'Correct-B-2.6', '198.51.100.10')
            ->assertSessionHasErrors(['phone' => $this->blockedMessage(15)]);

        $this->assertGuest();
        $this->assertSame(0, $accountB->fresh()->failed_attempts);
    }

    public function test_ac_3_account_lock_survives_a_change_of_address(): void
    {
        config()->set('login-security.max_failed_attempts', 2);
        config()->set('login-security.rate_limit_attempts', 10);
        $user = User::factory()->active()->create(['password' => 'Correct-Compte-2.6']);

        $this->login($user->phone, 'Errone-2.6', '198.51.100.11');
        $this->login($user->phone, 'Errone-2.6', '198.51.100.11')
            ->assertSessionHasErrors(['phone' => $this->blockedMessage(15)]);

        $this->login($user->phone, 'Correct-Compte-2.6', '198.51.100.12')
            ->assertSessionHasErrors(['phone' => $this->blockedMessage(15)]);

        $this->assertGuest();
    }

    public function test_ac_1_success_resets_the_failure_counter_and_is_recorded(): void
    {
        config()->set('login-security.max_failed_attempts', 5);
        config()->set('login-security.rate_limit_attempts', 10);
        $user = User::factory()->active()->create([
            'password' => 'Correct-Reinitialisation-2.6',
            'failed_attempts' => 2,
        ]);

        $this->login($user->phone, 'Correct-Reinitialisation-2.6')
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->fresh()->failed_attempts);
        $this->assertDatabaseHas('login_attempts', [
            'user_id' => $user->getKey(),
            'successful' => true,
        ]);
    }

    public function test_ac_1_known_and_unknown_lock_expirations_are_journalized_on_the_next_attempt(): void
    {
        $this->freezeTime();
        config()->set('login-security.max_failed_attempts', 1);
        config()->set('login-security.lockout_minutes', 1);
        config()->set('login-security.rate_limit_attempts', 10);
        $user = User::factory()->active()->create(['password' => 'Correct-Expiration-2.6']);
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);

        $this->login($user->phone, 'Errone-2.6', '198.51.100.20');
        $this->login('+22790999999', 'Errone-2.6', '198.51.100.21');
        $this->travel(61)->seconds();

        $this->login($user->phone, 'Correct-Expiration-2.6', '198.51.100.20')
            ->assertRedirect(route('home', absolute: false));
        $this->post(route('logout'));
        $this->login('+22790999999', 'Errone-2.6', '198.51.100.21')
            ->assertSessionHasErrors(['phone' => $this->blockedMessage(1)]);

        $this->assertSame(2, AuditLog::query()
            ->where('id', '>', $lastAuditId)
            ->where('action', 'login_lock_expired')
            ->count());
        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_ac_1_temporal_oracle_stays_closed_after_limit_and_limiter_window_expiration(): void
    {
        $this->freezeTime();
        config()->set('login-security.max_failed_attempts', 2);
        config()->set('login-security.lockout_minutes', 15);
        config()->set('login-security.rate_limit_attempts', 2);
        config()->set('login-security.rate_limit_decay_seconds', 60);
        $wrongPassword = 'Oracle-Temporel-Errone-2.6';
        $user = User::factory()->active()->create([
            'phone' => '+22790123456',
            'password' => 'Oracle-Temporel-Correct-2.6',
        ]);

        $this->login($user->phone, $wrongPassword, '198.51.100.31');
        $knownAtLimit = $this->login($user->phone, $wrongPassword, '198.51.100.31');
        $knownAtLimitSignature = $this->responseSignature($knownAtLimit);
        $this->login('+22790999999', $wrongPassword, '198.51.100.32');
        $unknownAtLimit = $this->login('+22790999999', $wrongPassword, '198.51.100.32');

        $this->assertSame($knownAtLimitSignature, $this->responseSignature($unknownAtLimit));

        $this->travel(61)->seconds();
        $knownAfterLimiter = $this->login($user->phone, $wrongPassword, '198.51.100.31');
        $knownAfterLimiterSignature = $this->responseSignature($knownAfterLimiter);
        $unknownAfterLimiter = $this->login('+22790999999', $wrongPassword, '198.51.100.32');

        $this->assertSame($knownAfterLimiterSignature, $this->responseSignature($unknownAfterLimiter));
        $knownAfterLimiter->assertSessionHasErrors(['phone' => $this->blockedMessage(15)]);
        $unknownAfterLimiter->assertSessionHasErrors(['phone' => $this->blockedMessage(15)]);
    }

    private function login(
        string $phone,
        string $password,
        string $ipAddress = '198.51.100.9',
    ): TestResponse {
        return $this->from(route('login'))
            ->withServerVariables(['REMOTE_ADDR' => $ipAddress])
            ->post(route('login.store'), compact('phone', 'password'));
    }

    private function blockedMessage(int $minutes): string
    {
        return "Trop de tentatives. Réessayez dans {$minutes} minutes, ou contactez la direction.";
    }

    /** @return array{status: int, location: ?string, errors: string} */
    private function responseSignature(TestResponse $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'location' => $response->headers->get('Location'),
            'errors' => serialize($response->getSession()->get('errors')),
        ];
    }
}
