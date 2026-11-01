<?php

namespace Tests\Feature\Http;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

class PasswordChangeMiddlewareTest extends IdentityTestCase
{
    /** @var list<string> */
    private array $blockedPaths = [
        '/__test/password-change/one',
        '/__test/password-change/two',
        '/__test/password-change/three',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->blockedPaths as $index => $path) {
            Route::middleware(['web', 'auth', 'account.active', 'password.changed'])
                ->get($path, fn () => response()->noContent())
                ->name("testing.password-change.blocked.{$index}");
        }
    }

    public function test_ac_4_three_other_routes_are_blocked_until_password_change(): void
    {
        $user = User::factory()->active()->create(['must_change_password' => true]);

        foreach ($this->blockedPaths as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertRedirect(route('password.change.edit'));
        }
    }

    public function test_ac_4_inertia_access_is_blocked_with_external_location_response(): void
    {
        $user = User::factory()->active()->create(['must_change_password' => true]);
        $version = app(HandleInertiaRequests::class)->version(request());

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', $version ?? '')
            ->get($this->blockedPaths[0])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('password.change.edit'));
    }

    public function test_ac_4_change_screen_submission_and_logout_are_excluded_from_the_loop(): void
    {
        $user = User::factory()->active()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->get(route('password.change.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Identity/ChangePassword'));

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** Décision de la direction : huit caractères suffisent, même s'ils ne sont que des chiffres. */
    public function test_eight_digits_are_accepted_and_seven_are_refused(): void
    {
        $user = User::factory()->active()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->patch(route('password.change.update'), ['password' => '1234567', 'password_confirmation' => '1234567'])
            ->assertSessionHasErrors(['password' => 'Le mot de passe doit contenir au moins 8 caractères (des chiffres seuls suffisent).']);
        $this->assertTrue($user->refresh()->must_change_password);

        $this->actingAs($user)
            ->patch(route('password.change.update'), ['password' => '12345678', 'password_confirmation' => '12345678'])
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Votre nouveau mot de passe est enregistré.');
        $this->assertTrue(Hash::check('12345678', $user->refresh()->password));
    }

    public function test_the_change_screen_tells_a_forced_change_from_a_voluntary_one(): void
    {
        $forced = User::factory()->active()->create(['must_change_password' => true]);
        $voluntary = User::factory()->active()->create(['must_change_password' => false]);

        $this->actingAs($forced)->get(route('password.change.edit'))
            ->assertInertia(fn ($page) => $page->component('Identity/ChangePassword')->where('forced', true));
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($voluntary)->get(route('password.change.edit'))
            ->assertInertia(fn ($page) => $page->component('Identity/ChangePassword')->where('forced', false));
    }

    public function test_ac_3_and_4_password_change_is_hashed_audited_and_unlocks_access(): void
    {
        $oldPassword = 'Ancien-Mot-De-Passe-2026';
        $newPassword = 'Nouveau-Mot-De-Passe-2026';
        $user = User::factory()->active()->create([
            'password' => $oldPassword,
            'must_change_password' => true,
        ]);
        $oldHash = (string) $user->getRawOriginal('password');
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);

        $this->actingAs($user)
            ->patch(route('password.change.update'), [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertRedirect(route('home'));

        $user->refresh();
        $audit = AuditLog::query()->where('id', '>', $lastAuditId)->sole();
        $serializedAudit = json_encode([
            $audit->old_values,
            $audit->new_values,
        ], JSON_THROW_ON_ERROR);

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check($oldPassword, $user->password));
        $this->assertSame('password_changed', $audit->action);
        $this->assertStringNotContainsString($oldPassword, $serializedAudit);
        $this->assertStringNotContainsString($newPassword, $serializedAudit);
        $this->assertStringNotContainsString($oldHash, $serializedAudit);
        $this->assertStringNotContainsStringIgnoringCase('password', $serializedAudit);
        $this->assertAuthenticatedAs($user);

        $this->get($this->blockedPaths[0])->assertNoContent();
    }
}
