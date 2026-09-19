<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
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
        preg_match('/[a-f0-9]{32}/', $response->getContent(), $matches);
        $temporaryPassword = $matches[0] ?? '';

        $this->assertSame(32, strlen($temporaryPassword));
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
        $this->assertStringContainsString('reste bloquée sans contournement', $source);
        $this->assertStringContainsString('ne sera jamais envoyé par WhatsApp', $source);
        $this->assertFileExists(base_path('docs/ops/password-reset.md'));
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
