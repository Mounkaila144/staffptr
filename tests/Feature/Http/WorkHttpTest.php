<?php

namespace Tests\Feature\Http;

use App\Enums\ObjectiveState;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Models\Work\Task;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class WorkHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    #[Test]
    public function ac_4_company_priorities_are_readable_by_all_six_roles_and_managed_by_direction_only(): void
    {
        foreach (['super_admin', 'direction', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            Auth::forgetGuards();
            $user = User::factory()->active()->withRole($role)->create();
            $this->actingAs($user)->get('/priorites-entreprise?month=2026-07')->assertOk();
        }
        session()->flush();
        Auth::forgetGuards();
        $employee = User::factory()->active()->withRole('employe')->create();
        $this->actingAs($employee)->post('/priorites-entreprise', [])->assertForbidden();
    }

    #[Test]
    public function ac_6_priority_empty_state_is_exact_and_creation_action_depends_on_permission(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $this->actingAs($direction)->get('/priorites-entreprise?month=2026-07')->assertInertia(fn (Assert $page): Assert => $page->component('Work/CompanyPriorities/Index')->where('emptyMessage', 'Aucune priorité définie pour juillet.')->where('canManage', true));
    }

    #[Test]
    public function ac_20_21_and_26_three_objective_views_share_the_server_visibility_scope(): void
    {
        $owner = User::factory()->active()->withRole('employe')->create();
        $outsider = User::factory()->active()->withRole('employe')->create();
        $objective = Objective::factory()->for($owner, 'owner')->create(['created_by' => $owner->getKey(), 'due_date' => '2026-07-20']);
        foreach (['/objectifs?month=2026-07', '/objectifs/calendrier?month=2026-07', '/objectifs/synthese?month=2026-07'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
        session()->flush();
        Auth::forgetGuards();
        $this->actingAs($outsider)->get("/objectifs/{$objective->getKey()}")->assertForbidden();
    }

    #[Test]
    public function ac_22_summary_includes_direction_accounts_without_validated_objective_and_has_no_ranking(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $response = $this->actingAs($direction)->get('/objectifs/synthese?month=2026-07');
        $response->assertInertia(fn (Assert $page): Assert => $page->component('Work/Objectives/Summary')->has('membersWithoutValidatedObjective', 1)->where('membersWithoutValidatedObjective.0.id', $direction->getKey()));
        $this->assertStringNotContainsString('classement', (string) $response->getContent());
    }

    #[Test]
    public function ac_25_filter_empty_state_is_distinct_from_real_empty_state_in_props_and_source(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();
        $this->actingAs($user)->get('/objectifs?month=2026-07&state=bloque')->assertInertia(fn (Assert $page): Assert => $page->where('filtersActive', true));
        $source = file_get_contents(resource_path('js/Pages/Work/Objectives/Index.vue'));
        $this->assertStringContainsString('Aucun objectif ne correspond à ces filtres.', $source);
    }

    #[Test]
    public function ac_29_project_budget_direct_url_is_limited_to_direction_and_finance(): void
    {
        $manager = User::factory()->active()->create();
        $project = Project::factory()->for($manager, 'manager')->create();
        foreach (['direction', 'finance'] as $role) {
            session()->flush();
            Auth::forgetGuards();
            $user = User::factory()->active()->withRole($role)->create();
            $this->actingAs($user)->get("/projets/{$project->getKey()}/budget")->assertOk();
        }
        foreach (['super_admin', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            Auth::forgetGuards();
            $user = User::factory()->active()->withRole($role)->create();
            $this->actingAs($user)->get("/projets/{$project->getKey()}/budget")->assertForbidden();
        }
    }

    #[Test]
    public function ac_32_project_empty_message_is_exact(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();
        $this->actingAs($user)->get('/projets')->assertInertia(fn (Assert $page): Assert => $page->where('emptyMessage', 'Aucun projet actif.'));
    }

    #[Test]
    public function ac_38_today_tasks_need_no_filter_and_only_show_the_authenticated_users_day(): void
    {
        $user = User::factory()->active()->withRole('employe')->create();
        $other = User::factory()->active()->create();
        Task::factory()->for($user, 'assignee')->create(['created_by' => $user->getKey(), 'due_date' => now('Africa/Niamey')->toDateString()]);
        Task::factory()->for($other, 'assignee')->create(['created_by' => $other->getKey(), 'due_date' => now('Africa/Niamey')->toDateString()]);
        $this->actingAs($user)->get('/taches/aujourdhui')->assertInertia(fn (Assert $page): Assert => $page->component('Work/Tasks/Today')->has('tasks', 1));
    }

    #[Test]
    public function ac_43_to_49_dashboard_orders_approval_then_work_blocks_hides_forbidden_blocks_and_caps_queries(): void
    {
        $this->withoutExceptionHandling();
        $direction = User::factory()->active()->withRole('direction')->create();
        Objective::factory()->for($direction, 'owner')->create(['created_by' => $direction->getKey(), 'state' => ObjectiveState::EnCours, 'due_date' => now('Africa/Niamey')->endOfMonth()->toDateString()]);
        Task::factory()->for($direction, 'assignee')->create(['created_by' => $direction->getKey(), 'due_date' => now('Africa/Niamey')->toDateString()]);
        Cache::flush();
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries++;
        });
        $this->actingAs($direction)->get('/')->assertInertia(fn (Assert $page): Assert => $page->component('Identity/Home')->has('approvalQueue')->has('workBlocks.objectives.items', 1)->has('workBlocks.todayTasks.items', 1)->has('workBlocks.deadlines')->has('workBlocks.notifications'));
        $this->assertLessThanOrEqual(20, $queries, 'Le tableau de bord dépasse le plafond de 20 requêtes.');
        session()->flush();
        Auth::forgetGuards();
        $superAdmin = User::factory()->active()->withRole('super_admin')->create();
        $this->actingAs($superAdmin)->get('/')->assertInertia(fn (Assert $page): Assert => $page->where('workBlocks.objectives', null)->where('workBlocks.todayTasks', null)->where('workBlocks.deadlines', null)->where('workBlocks.notifications', null));
        $source = file_get_contents(resource_path('js/Pages/Identity/Home.vue'));
        $this->assertLessThan(strpos($source, 'monthly-objectives-title'), strpos($source, 'data-testid="approval-queue-block"'));
    }
}
