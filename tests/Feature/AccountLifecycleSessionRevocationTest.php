<?php

namespace Tests\Feature;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\IdentityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IdentityTestCase;

class AccountLifecycleSessionRevocationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cette règle est un faux vert avec SESSION_DRIVER=array : aucune ligne ne serait créée,
        // donc une révocation défaillante semblerait réussir. Ces tests forcent le pilote réel.
        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);
        app('session')->forgetDrivers();
    }

    public function test_blocking_rule_10_suspension_revokes_two_database_sessions_immediately(): void
    {
        $password = 'Sessions-Suspension-2026';
        $user = User::factory()->active()->create(['password' => $password]);
        [$firstSession, $secondSession] = $this->openTwoSessions($user, $password);

        app(IdentityService::class)->changeUserState(
            $user,
            UserState::Suspendu,
            null,
            'Direction test',
            "Suspension immédiate de l'accès",
        );

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->getKey())->count());
        $audit = AuditLog::query()->where('action', 'user_state_changed')->sole();
        $this->assertSame(2, $audit->new_values['sessions_revoked'] ?? null);

        $this->assertSessionIsRejected($firstSession);
        $this->assertSessionIsRejected($secondSession);
    }

    public function test_blocking_rule_10_reactivation_never_resurrects_revoked_sessions(): void
    {
        $password = 'Sessions-Reactivatees-2026';
        $user = User::factory()->active()->create(['password' => $password]);
        [$firstSession, $secondSession] = $this->openTwoSessions($user, $password);
        $service = app(IdentityService::class);

        $service->changeUserState(
            $user,
            UserState::Suspendu,
            null,
            'Direction test',
            "Suspension immédiate de l'accès",
        );
        $service->changeUserState(
            $user,
            UserState::Actif,
            null,
            'Direction test',
            'Réactivation contrôlée',
        );

        $this->assertSame(UserState::Actif, $user->fresh()->state);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->getKey())->count());
        $this->assertSessionIsRejected($firstSession);
        $this->assertSessionIsRejected($secondSession);
    }

    public function test_ac_2_password_change_revokes_every_old_session_and_reestablishes_current_one(): void
    {
        $oldPassword = 'Sessions-Ancien-Secret-2026';
        $newPassword = 'Sessions-Nouveau-Secret-2026';
        $user = User::factory()->active()->create(['password' => $oldPassword]);
        [$currentSession, $otherSession] = $this->openTwoSessions($user, $oldPassword);

        $this->resetHttpState();
        $response = $this->withCookie($this->sessionCookieName(), $currentSession)
            ->patch(route('password.change.update'), [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect(route('home'));

        $replacementCookie = $response->getCookie($this->sessionCookieName());
        $this->assertNotNull($replacementCookie);
        $replacementSession = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->sole()
            ->id;
        $this->assertIsString($replacementSession);
        $this->assertNotSame($currentSession, $replacementSession);
        $this->assertDatabaseMissing('sessions', ['id' => $currentSession]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSession]);
        $this->assertDatabaseHas('sessions', [
            'id' => $replacementSession,
            'user_id' => $user->getKey(),
        ]);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->getKey())->count());

        $audit = AuditLog::query()->where('action', 'password_changed')->sole();
        $this->assertSame(2, $audit->new_values['sessions_revoked'] ?? null);
        $serializedAudit = json_encode([
            $audit->old_values,
            $audit->new_values,
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsStringIgnoringCase('password', $serializedAudit);
        $this->assertStringNotContainsString($oldPassword, $serializedAudit);
        $this->assertStringNotContainsString($newPassword, $serializedAudit);

        $this->assertSessionIsRejected($currentSession);
        $this->assertSessionIsRejected($otherSession);

        $this->resetHttpState();
        $this->withCookie($this->sessionCookieName(), $replacementSession)
            ->get(route('home'))
            ->assertOk();
    }

    public function test_ac_2_third_party_password_change_revokes_target_sessions_not_actor_session(): void
    {
        $actorPassword = 'Direction-Session-2026';
        $targetPassword = 'Cible-Ancien-Secret-2026';
        $actor = User::factory()->active()->create(['password' => $actorPassword]);
        $target = User::factory()->active()->create(['password' => $targetPassword]);
        $actorSession = $this->openSession($actor, $actorPassword);
        [$firstTargetSession, $secondTargetSession] = $this->openTwoSessions($target, $targetPassword);

        app(IdentityService::class)->changePassword(
            $target,
            'Cible-Nouveau-Secret-2026',
            (int) $actor->getKey(),
            'Direction test',
            'Réinitialisation future par la direction',
        );

        $this->assertDatabaseHas('sessions', [
            'id' => $actorSession,
            'user_id' => $actor->getKey(),
        ]);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->getKey())->count());
        $this->assertSessionIsRejected($firstTargetSession);
        $this->assertSessionIsRejected($secondTargetSession);

        $this->resetHttpState();
        $this->withCookie($this->sessionCookieName(), $actorSession)
            ->get(route('home'))
            ->assertOk();
    }

    /** @return array{string, string} */
    private function openTwoSessions(User $user, string $password): array
    {
        return [
            $this->openSession($user, $password),
            $this->openSession($user, $password),
        ];
    }

    private function openSession(User $user, string $password): string
    {
        $existingSessionIds = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->pluck('id')
            ->all();
        $this->resetHttpState();
        $response = $this->withCookie($this->sessionCookieName(), Str::random(40))
            ->post(route('login.store'), [
                'phone' => $user->phone,
                'password' => $password,
            ])
            ->assertRedirect(route('home', absolute: false));

        $cookie = $response->getCookie($this->sessionCookieName());
        $this->assertNotNull($cookie);
        $newSessions = DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when(
                $existingSessionIds !== [],
                fn ($query) => $query->whereNotIn('id', $existingSessionIds),
            )
            ->pluck('id');
        $this->assertCount(1, $newSessions);
        $sessionId = $newSessions->sole();
        $this->assertIsString($sessionId);
        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
            'user_id' => $user->getKey(),
        ]);

        return $sessionId;
    }

    private function assertSessionIsRejected(string $sessionId): void
    {
        $this->resetHttpState();
        $this->withCookie($this->sessionCookieName(), $sessionId)
            ->get(route('home'))
            ->assertRedirect(route('login'));
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
