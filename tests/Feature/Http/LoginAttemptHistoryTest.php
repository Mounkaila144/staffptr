<?php

namespace Tests\Feature\Http;

use App\Models\Identity\LoginAttempt;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

class LoginAttemptHistoryTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_4_direction_can_view_successes_failures_and_open_sessions(): void
    {
        $direction = $this->userWithRole('direction');
        $person = Person::factory()->create(['full_name' => 'Aïcha Garba']);
        $user = User::factory()->active()->for($person)->create();
        LoginAttempt::factory()->for($user)->create([
            'successful' => false,
            'ip_address' => '198.51.100.40',
            'user_agent' => 'Firefox Mobile',
            'occurred_at' => CarbonImmutable::parse('2026-07-20 23:30:00', 'UTC'),
        ]);
        LoginAttempt::factory()->for($user)->create([
            'successful' => true,
            'occurred_at' => CarbonImmutable::parse('2026-07-20 22:30:00', 'UTC'),
        ]);
        DB::table('sessions')->insert([
            'id' => 'session-story-2-6',
            'user_id' => $user->getKey(),
            'ip_address' => '203.0.113.20',
            'user_agent' => 'Safari iPhone',
            'payload' => 'fixture',
            'last_activity' => CarbonImmutable::parse('2026-07-20 23:45:00', 'UTC')->timestamp,
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-07-21 00:00:00', 'UTC'));

        $this->actingAs($direction)
            ->get(route('login-attempts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Identity/LoginAttempts/Index')
                ->has('attempts.data', 2)
                ->where('attempts.data.0.person', 'Aïcha Garba')
                ->where('attempts.data.0.device', 'Firefox Mobile')
                ->where('attempts.data.0.address', '198.51.100.40')
                ->where('attempts.data.0.occurred_at', '21/07/2026 00:30')
                ->where('attempts.data.0.result', 'Échouée')
                ->where('attempts.data.1.result', 'Réussie')
                ->has('sessions', 1)
                ->where('sessions.0.person', 'Aïcha Garba')
                ->where('sessions.0.device', 'Safari iPhone')
                ->where('sessions.0.address', '203.0.113.20'));

        $this->assertDatabaseHas('sessions', ['id' => 'session-story-2-6']);
    }

    public function test_ac_5_history_filters_by_person_and_period_and_paginates(): void
    {
        $direction = $this->userWithRole('direction');
        $selected = User::factory()->active()->for(
            Person::factory()->create(['full_name' => 'Personne sélectionnée']),
        )->create();
        $other = User::factory()->active()->create();
        LoginAttempt::factory()->for($selected)->create([
            'occurred_at' => CarbonImmutable::parse('2026-07-10 08:00:00', 'UTC'),
        ]);
        LoginAttempt::factory()->for($selected)->create([
            'occurred_at' => CarbonImmutable::parse('2026-06-01 08:00:00', 'UTC'),
        ]);
        LoginAttempt::factory()->for($other)->create([
            'occurred_at' => CarbonImmutable::parse('2026-07-10 08:00:00', 'UTC'),
        ]);

        $this->actingAs($direction)
            ->get(route('login-attempts.index', [
                'person_id' => $selected->person_id,
                'from' => '2026-07-01',
                'to' => '2026-07-20',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('attempts.data', 1)
                ->where('attempts.data.0.person', 'Personne sélectionnée')
                ->where('filters.person_id', $selected->person_id)
                ->where('filters.from', '2026-07-01')
                ->where('filters.to', '2026-07-20')
                ->where('filtersActive', true)
                ->has('attempts.current_page')
                ->has('attempts.last_page'));

        $this->actingAs($direction)
            ->get(route('login-attempts.index', [
                'person_id' => $selected->person_id,
                'to' => '2026-06-05',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('attempts.data', 1)
                ->where('attempts.data.0.person', 'Personne sélectionnée'));
    }

    public function test_ac_5_filtered_empty_state_is_distinct_from_the_real_empty_state(): void
    {
        $direction = $this->userWithRole('direction');
        $person = Person::factory()->create();

        $this->actingAs($direction)
            ->get(route('login-attempts.index', ['person_id' => $person->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('attempts.data', 0)
                ->where('filtersActive', true)
                ->where('hasFailedAttemptsLast30Days', false));

        $pageSource = (string) file_get_contents(
            resource_path('js/Pages/Identity/LoginAttempts/Index.vue'),
        );
        $this->assertStringContainsString(
            'Aucune tentative échouée sur les 30 derniers jours.',
            $pageSource,
        );
        $this->assertStringContainsString('Aucune connexion ne correspond à ces filtres.', $pageSource);
        $this->assertStringContainsString('Réinitialiser les filtres', $pageSource);
        $this->assertStringContainsString('sm:grid-cols-2', $pageSource);
    }

    public function test_ac_4_all_five_non_direction_roles_receive_403_on_direct_url(): void
    {
        foreach (['super_admin', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $response = $this->actingAs($this->userWithRole($role))
                ->get(route('login-attempts.index'));

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Le rôle {$role} a reçu {$response->getStatusCode()} vers ".
                    ($response->headers->get('Location') ?? 'aucune redirection'),
            );
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test story 2.6');

        return $user;
    }
}
