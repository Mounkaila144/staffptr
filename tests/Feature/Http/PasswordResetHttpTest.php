<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

class PasswordResetHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        config([
            'services.evolution.url' => 'https://evolution.test',
            'services.evolution.key' => 'evolution-test-key',
            'services.evolution.instance' => 'ptr-test',
        ]);
    }

    public function test_ac_1_only_direction_and_super_admin_can_reset_and_no_public_reset_route_exists(): void
    {
        $this->fakeEvolution();
        $target = User::factory()->active()->create();

        foreach (['direction', 'super_admin'] as $role) {
            session()->flush();
            $actor = $this->userWithRole($role);

            $this->actingAs($actor)
                ->post(route('accounts.password-reinitialization.initiate', $target))
                ->assertOk();
        }

        foreach (['finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $actor = $this->userWithRole($role);

            $this->actingAs($actor)
                ->post(route('accounts.password-reinitialization.initiate', $target))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('accounts.password-reinitialization.confirm', $target), ['confirmation_code' => '123456'])
                ->assertForbidden();
        }

        foreach (['password.request', 'password.reset'] as $forbiddenName) {
            $this->assertFalse(Route::has($forbiddenName));
        }

        foreach (['accounts.password-reinitialization.initiate', 'accounts.password-reinitialization.confirm'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_ac_2_code_is_required_before_the_temporary_password_and_secret_is_returned_once(): void
    {
        $this->fakeEvolution();
        $actor = $this->userWithRole('direction');
        $target = User::factory()->active()->create(['password' => 'Ancien-Mot-De-Passe']);

        $initiation = $this->actingAs($actor)
            ->post(route('accounts.password-reinitialization.initiate', $target));
        $initiation->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Identity/Accounts/Index')
            ->where('passwordResetChallenge.user_id', $target->getKey())
            ->missing('resetAccount'));
        $this->assertStringNotContainsString('temporary_password', $initiation->getContent());

        $code = $this->sentConfirmationCode();
        $response = $this->post(route('accounts.password-reinitialization.confirm', $target), [
            'confirmation_code' => $code,
        ]);
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Identity/Accounts/Index')
            ->where('resetAccount.person_name', $target->person->full_name)
            ->missing('passwordResetChallenge'));
        preg_match('/temporary_password(?:&quot;|"):(?:&quot;|")([0-9]{8})(?:&quot;|")/', $response->getContent(), $matches);
        $temporaryPassword = $matches[1] ?? '';

        $this->assertMatchesRegularExpression('/\A[0-9]{8}\z/', $temporaryPassword);
        $this->assertSame(1, substr_count($response->getContent(), $temporaryPassword));
        $this->assertStringNotContainsString($temporaryPassword, serialize(session()->all()));
        $this->assertTrue($target->fresh()->must_change_password);

        $nextResponse = $this->get(route('accounts.index'));
        $nextResponse->assertOk()->assertInertia(fn (Assert $page) => $page->missing('resetAccount'));
        $this->assertStringNotContainsString($temporaryPassword, $nextResponse->getContent());

        Http::assertSentCount(2);
        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/message/sendText/')
            && ! str_contains((string) $request['text'], $temporaryPassword));
    }

    public function test_a_wrong_code_keeps_the_code_entry_on_screen_and_pages_keep_the_list_address(): void
    {
        $this->fakeEvolution();
        $actor = $this->userWithRole('direction');
        $target = User::factory()->active()->create();

        $this->actingAs($actor)
            ->post(route('accounts.password-reinitialization.initiate', $target))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->url('/comptes'));

        $code = $this->sentConfirmationCode();
        $wrongCode = $code === '000000' ? '111111' : '000000';

        $this->post(route('accounts.password-reinitialization.confirm', $target), ['confirmation_code' => $wrongCode])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->url('/comptes')
                ->where('passwordResetChallenge.user_id', $target->getKey())
                ->has('errors.confirmation_code')
                ->missing('resetAccount'));

        $this->post(route('accounts.password-reinitialization.confirm', $target), ['confirmation_code' => $code])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->url('/comptes')
                ->has('resetAccount.temporary_password'));

        $this->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page->url('/comptes'));
    }

    public function test_ac_5_evolution_outage_blocks_reset_and_screen_references_the_procedure(): void
    {
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'close']], 200),
        ]);
        $actor = $this->userWithRole('direction');
        $target = User::factory()->active()->create(['password' => 'Secret-Inchange']);
        $originalHash = $target->password;

        $this->actingAs($actor)
            ->post(route('accounts.password-reinitialization.initiate', $target))
            ->assertSessionHasErrors('password_reset');

        $this->assertSame($originalHash, $target->fresh()->password);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'password_reset_by_administrator']);

        $source = (string) file_get_contents(resource_path('js/Pages/Identity/Accounts/Index.vue'));
        $this->assertStringContainsString('SensitiveConfirmation', $source);
        $this->assertStringContainsString('code à 6 chiffres', $source);
        // Décision du propriétaire (2026-11-01) : la direction peut réinitialiser sans code.
        $this->assertStringContainsString('seule la direction peut réinitialiser sans code', $source);
        $this->assertStringContainsString('ne sera jamais envoyé par WhatsApp', $source);
        $this->assertFileExists(base_path('docs/ops/password-reset.md'));
    }

    public function test_direction_can_reset_without_whatsapp_with_an_audited_reason(): void
    {
        Http::fake();
        $actor = $this->userWithRole('direction');
        $target = User::factory()->active()->create([
            'password' => 'Ancien-Mot-De-Passe',
            'failed_attempts' => 5,
            'locked_until' => now()->addDay(),
        ]);

        $response = $this->actingAs($actor)->post(route('accounts.password-reinitialization.direct', $target), [
            'verification' => 'Vue en personne au bureau ce matin.',
        ]);

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Identity/Accounts/Index')
            ->where('resetAccount.phone', $target->phone)
            ->has('resetAccount.temporary_password'));
        $fresh = $target->fresh();
        $this->assertTrue((bool) $fresh->must_change_password);
        $this->assertSame(0, (int) $fresh->failed_attempts);
        $this->assertNull($fresh->locked_until);
        $this->assertNotSame($target->password, $fresh->password);
        Http::assertNothingSent();

        $audit = AuditLog::query()->where('action', 'password_reset_without_whatsapp')->sole();
        $this->assertSame($actor->getKey(), (int) $audit->actor_id);
        $this->assertStringContainsString('Vue en personne au bureau ce matin.', (string) $audit->reason);
        $this->assertArrayNotHasKey('temporary_password', $audit->new_values);
    }

    public function test_reset_without_whatsapp_is_refused_to_others_to_oneself_and_without_reason(): void
    {
        $target = User::factory()->active()->create();

        foreach (['super_admin', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $this->actingAs($this->userWithRole($role))
                ->post(route('accounts.password-reinitialization.direct', $target), ['verification' => 'Vue en personne ce matin.'])
                ->assertForbidden();
        }

        session()->flush();
        $director = $this->userWithRole('direction');
        $this->actingAs($director)
            ->post(route('accounts.password-reinitialization.direct', $director), ['verification' => 'Je me réinitialise moi-même.'])
            ->assertForbidden();

        $this->actingAs($director)
            ->post(route('accounts.password-reinitialization.direct', $target), ['verification' => 'court'])
            ->assertSessionHasErrors('verification');

        $this->assertDatabaseMissing('audit_logs', ['action' => 'password_reset_without_whatsapp']);
    }

    private function fakeEvolution(): void
    {
        Http::fake([
            'https://evolution.test/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']], 200),
            'https://evolution.test/message/sendText/*' => Http::response(['key' => ['id' => 'message-id']], 201),
        ]);
    }

    private function sentConfirmationCode(): string
    {
        $request = collect(Http::recorded())
            ->map(static fn (array $record): Request => $record[0])
            ->first(static fn (Request $request): bool => str_contains($request->url(), '/message/sendText/'));
        $this->assertInstanceOf(Request::class, $request);
        preg_match('/\b([0-9]{6})\b/', (string) $request['text'], $matches);
        $code = $matches[1] ?? '';
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);
        $this->assertSame('227'.substr($request['number'], 3), $request['number']);
        $this->assertSame('evolution-test-key', $request->header('apikey')[0] ?? null);

        return $code;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test story 2.8');

        return $user;
    }
}
