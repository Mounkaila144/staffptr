<?php

namespace Tests\Feature\Http;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

class AccountAdministrationHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_1_direction_and_super_admin_can_open_and_create_but_four_other_roles_receive_403(): void
    {
        foreach (['direction', 'super_admin'] as $role) {
            session()->flush();
            $actor = $this->userWithRole($role);

            $this->actingAs($actor)->get(route('accounts.index'))->assertOk();
        }

        foreach (['finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $actor = $this->userWithRole($role);

            $this->actingAs($actor)->get(route('accounts.index'))->assertForbidden();
            $this->actingAs($actor)->post(route('accounts.store'), $this->newAccountPayload())->assertForbidden();
        }
    }

    public function test_ac_2_temporary_password_is_only_in_the_immediate_response_not_session_audit_log_or_next_response(): void
    {
        $actor = $this->userWithRole('direction');
        $logPath = storage_path('logs/laravel.log');
        $logOffset = is_file($logPath) ? (int) filesize($logPath) : 0;

        $response = $this->actingAs($actor)->post(route('accounts.store'), $this->newAccountPayload());
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Identity/Accounts/Index')
            ->where('createdAccount.person_name', 'Nouvelle Personne')
            ->where('createdAccount.phone', '+22790112233'));
        preg_match('/[a-f0-9]{32}/', $response->getContent(), $matches);
        $temporaryPassword = $matches[0] ?? '';

        $this->assertSame(32, strlen($temporaryPassword));
        $this->assertSame(1, substr_count($response->getContent(), $temporaryPassword));
        $this->assertStringNotContainsString($temporaryPassword, serialize(session()->all()));

        $nextResponse = $this->get(route('accounts.index'));
        $nextResponse->assertOk()->assertInertia(fn (Assert $page) => $page->missing('createdAccount'));
        $this->assertStringNotContainsString($temporaryPassword, $nextResponse->getContent());

        $serializedAudit = AuditLog::query()->get()->map(static fn (AuditLog $audit): string => json_encode([
            $audit->old_values,
            $audit->new_values,
        ], JSON_THROW_ON_ERROR))->implode('\n');
        $newLogContent = is_file($logPath) ? (string) file_get_contents($logPath, offset: $logOffset) : '';
        $this->assertStringNotContainsString($temporaryPassword, $serializedAudit);
        $this->assertStringNotContainsString($temporaryPassword, $newLogContent);
    }

    public function test_ac_3_accounts_include_person_state_roles_and_can_be_filtered_by_state_and_role(): void
    {
        $actor = $this->userWithRole('direction');
        $finance = User::factory()->suspended()->create();
        app(RoleAssignmentService::class)->assignRole($finance, 'finance', $actor->getKey(), 'Direction test');
        $this->userWithRole('employe');

        $this->actingAs($actor)
            ->get(route('accounts.index', ['state' => 'suspendu', 'role' => 'finance']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Identity/Accounts/Index')
                ->has('accounts.data', 1)
                ->where('accounts.data.0.id', $finance->getKey())
                ->where('accounts.data.0.state', 'suspendu')
                ->where('accounts.data.0.roles.0', 'finance')
                ->where('accounts.data.0.person.id', $finance->person_id)
                ->where('filtersActive', true));
    }

    public function test_ac_4_role_assignment_and_removal_from_screen_are_audited_with_actor_and_values(): void
    {
        $actor = $this->userWithRole('direction');
        $target = $this->userWithRole('employe');
        $lastAuditId = (int) (AuditLog::query()->max('id') ?? 0);

        $this->actingAs($actor)->patch(route('accounts.roles.sync', $target), [
            'roles' => ['employe', 'tuteur'],
            'reason' => 'Responsabilité de tutorat',
        ])->assertRedirect(route('accounts.index'));
        $this->actingAs($actor)->patch(route('accounts.roles.sync', $target), [
            'roles' => ['tuteur'],
            'reason' => 'Fin du rôle employé',
        ])->assertRedirect(route('accounts.index'));

        $audits = AuditLog::query()->where('id', '>', $lastAuditId)->orderBy('id')->get();
        $this->assertSame(['user_roles_changed', 'user_roles_changed'], $audits->pluck('action')->all());
        $this->assertSame(['employe'], $audits[0]->old_values['roles']);
        $this->assertSame(['employe', 'tuteur'], $audits[0]->new_values['roles']);
        $this->assertSame(['employe', 'tuteur'], $audits[1]->old_values['roles']);
        $this->assertSame(['tuteur'], $audits[1]->new_values['roles']);
        $this->assertTrue($audits->every(fn (AuditLog $audit): bool => $audit->actor_id === $actor->getKey()));
    }

    public function test_ac_5_only_motivated_archiving_exists_and_person_lifecycle_remains_unchanged(): void
    {
        $actor = $this->userWithRole('direction');
        $target = User::factory()->active()->create();
        $personStatus = $target->person->operational_status;

        $this->actingAs($actor)->patch(route('accounts.archive', $target), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(UserState::Actif, $target->fresh()->state);

        $this->actingAs($actor)->patch(route('accounts.archive', $target), [
            'reason' => 'Départ confirmé par la direction',
        ])->assertRedirect(route('accounts.index'));

        $target->refresh();
        $audit = AuditLog::query()->where('action', 'user_state_changed')->latest('id')->firstOrFail();
        $this->assertSame(UserState::Archive, $target->state);
        $this->assertSame($personStatus, $target->person->operational_status);
        $this->assertSame('Départ confirmé par la direction', $audit->reason);
        $this->assertFalse(Route::has('accounts.destroy'));
        $this->assertCount(0, collect(Route::getRoutes()->getRoutes())->filter(
            static fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'comptes')
                && in_array('DELETE', $route->methods(), true),
        ));
    }

    public function test_ac_6_first_launch_incomplete_readiness_and_filter_empty_states_are_distinct(): void
    {
        $actor = $this->userWithRole('super_admin');

        $this->actingAs($actor)->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('readiness.first_launch', true)
                ->where('readiness.message', 'Seul votre compte existe. Créez les deux comptes de direction pour rendre les dépenses approuvables.'));

        $this->userWithRole('employe');
        $this->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('readiness.first_launch', false)
                ->where('readiness.approval_available', false)
                ->where('readiness.message', 'Les dépenses ne sont pas encore approuvables : deux comptes direction sont nécessaires.'));

        $this->get(route('accounts.index', ['role' => 'finance']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('accounts.data', 0)
                ->where('filtersActive', true)
                ->where('readiness.first_launch', false));
    }

    public function test_ac_7_account_page_is_card_based_and_has_no_table_or_delete_action(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Identity/Accounts/Index.vue'));

        $this->assertStringContainsString('<article v-for="account in accounts.data"', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringContainsString('StatusBadge', $source);
        $this->assertStringContainsString('EmptyState', $source);
        $this->assertStringContainsString('ActionCard', $source);
        $this->assertStringContainsString('AppButton', $source);
        $this->assertStringContainsString('FormField', $source);
        $this->assertStringNotContainsString('<table', $source);
        $this->assertStringNotContainsString('Supprimer', $source);
    }

    /** @return array<string, mixed> */
    private function newAccountPayload(): array
    {
        return [
            'person_mode' => 'new',
            'full_name' => 'Nouvelle Personne',
            'first_seen_at' => '2026-07-20',
            'phone' => '90 11 22 33',
            'roles' => ['finance'],
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test story 2.7');

        return $user;
    }
}
