<?php

namespace Tests\Feature;

use App\Enums\UserState;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IdentityTestCase;

class LoginLockoutSessionPreservationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);
        app('session')->forgetDrivers();
    }

    public function test_ac_1_lockout_keeps_existing_session_open_and_account_active(): void
    {
        config()->set('login-security.max_failed_attempts', 2);
        config()->set('login-security.rate_limit_attempts', 10);
        $password = 'Session-Conservee-2.6';
        $user = User::factory()->active()->create(['password' => $password]);
        $sessionId = $this->openSession($user, $password);

        $this->resetHttpState();
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.80'])
                ->post(route('login.store'), [
                    'phone' => $user->phone,
                    'password' => 'Mot-De-Passe-Errone-2.6',
                ]);
        }

        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
            'user_id' => $user->getKey(),
        ]);
        $this->assertSame(UserState::Actif, $user->fresh()->state);
        $this->assertNotNull($user->fresh()->locked_until);

        $this->resetHttpState();
        $this->withCookie($this->sessionCookieName(), $sessionId)
            ->get(route('home'))
            ->assertOk();
    }

    private function openSession(User $user, string $password): string
    {
        $response = $this->withCookie($this->sessionCookieName(), Str::random(40))
            ->post(route('login.store'), [
                'phone' => $user->phone,
                'password' => $password,
            ])
            ->assertRedirect(route('home', absolute: false));

        $this->assertNotNull($response->getCookie($this->sessionCookieName()));
        $sessionId = DB::table('sessions')->where('user_id', $user->getKey())->sole()->id;
        $this->assertIsString($sessionId);

        return $sessionId;
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
