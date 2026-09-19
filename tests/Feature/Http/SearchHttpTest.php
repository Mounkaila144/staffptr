<?php

namespace Tests\Feature\Http;

use App\Enums\ObjectiveState;
use App\Enums\ProjectStatus;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Services\Platform\SearchService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

/**
 * Story 10.1, Task 2 — recherche transverse et son cloisonnement (AC 1 à 6, AC 22).
 */
class SearchHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    /** AC 1 — la recherche couvre personne, projet et objectif. */
    public function test_ac_1_search_covers_people_projects_and_objectives(): void
    {
        $direction = $this->userWithRole('direction');
        Person::factory()->create(['full_name' => 'Amina Zakari']);
        Project::factory()->create(['name' => 'Portail Zakari']);
        Objective::factory()->create(['title' => 'Livrer Zakari', 'user_id' => $direction->getKey()]);

        $results = app(SearchService::class)->search($direction, ['term' => 'Zakari']);
        $counts = collect($results['groups'])->keyBy('key')->map(fn (array $g): int => $g['count']);

        $this->assertSame(1, $counts['person']);
        $this->assertSame(1, $counts['project']);
        $this->assertSame(1, $counts['objective']);
        $this->assertSame(3, $results['total']);
    }

    /**
     * AC 2 — **le test que la story réclame** : les six rôles cherchent le même terme sur le même
     * jeu de données, et chacun ne voit que son périmètre.
     */
    public function test_ac_2_six_roles_searching_the_same_data_each_see_only_their_own_scope(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $other = $this->userWithRole('employe');

        // Un objectif par employé : chacun ne doit voir que le sien, la direction les deux.
        Objective::factory()->create(['title' => 'Cible commune', 'user_id' => $employee->getKey()]);
        Objective::factory()->create(['title' => 'Cible commune', 'user_id' => $other->getKey()]);

        $seen = [];

        foreach (['direction', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $viewer = $role === 'employe' ? $employee : $this->userWithRole($role);
            $results = app(SearchService::class)->search($viewer, ['term' => 'Cible commune', 'type' => 'objective']);
            $seen[$role] = $results['groups'][0]['count'];
        }

        $this->assertSame(2, $seen['direction'], 'La direction voit les deux objectifs.');
        $this->assertSame(1, $seen['employe'], 'Un employé ne voit que le sien.');
        $this->assertSame(0, $seen['finance'], 'Un compte finance sans objectif ne voit rien ici.');
        $this->assertSame(0, $seen['stagiaire'], 'Un stagiaire sans objectif ne voit rien ici.');

        // `super_admin` n'a aucune permission métier : il n'accède pas à la recherche (règle 9).
        $this->assertFalse($this->userWithRole('super_admin')->can('recherche.utiliser'));
    }

    /**
     * AC 3 — un résultat interdit n'apparaît **ni en titre, ni en extrait, ni en compteur**. Le
     * compteur est le point sensible : c'est lui qui trahirait l'existence d'un objet caché.
     */
    public function test_ac_3_a_forbidden_result_leaks_neither_title_excerpt_nor_count(): void
    {
        $employee = $this->userWithRole('employe');
        $other = $this->userWithRole('employe');
        Objective::factory()->create(['title' => 'Secret industriel', 'user_id' => $other->getKey()]);

        $results = app(SearchService::class)->search($employee, ['term' => 'Secret']);
        $payload = json_encode($results, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $results['total'], 'Le compteur ne doit pas révéler un objet caché.');
        $this->assertStringNotContainsString('Secret industriel', $payload);

        foreach ($results['groups'] as $group) {
            $this->assertSame(0, $group['count']);
            $this->assertSame([], $group['items']);
        }
    }

    /** AC 1 — la période et le statut filtrent selon la sémantique propre à chaque type. */
    public function test_ac_1_period_and_status_filter_by_type(): void
    {
        $direction = $this->userWithRole('direction');
        $today = CarbonImmutable::now('Africa/Niamey');
        Objective::factory()->create([
            'title' => 'Cible datée', 'user_id' => $direction->getKey(),
            'due_date' => $today->addDays(10)->toDateString(), 'state' => ObjectiveState::EnCours->value,
        ]);
        Objective::factory()->create([
            'title' => 'Cible datée', 'user_id' => $direction->getKey(),
            'due_date' => $today->addDays(90)->toDateString(), 'state' => ObjectiveState::Atteint->value,
        ]);

        $windowed = app(SearchService::class)->search($direction, [
            'term' => 'Cible datée', 'type' => 'objective',
            'from' => $today->toDateString(), 'to' => $today->addDays(30)->toDateString(),
        ]);
        $byStatus = app(SearchService::class)->search($direction, [
            'term' => 'Cible datée', 'type' => 'objective', 'status' => ObjectiveState::Atteint->value,
        ]);

        $this->assertSame(1, $windowed['groups'][0]['count']);
        $this->assertSame(1, $byStatus['groups'][0]['count']);
    }

    /** AC 5 — l'état initial et le vide de recherche portent deux messages distincts. */
    public function test_ac_5_initial_state_and_empty_result_carry_different_messages(): void
    {
        $direction = $this->userWithRole('direction');

        $initial = app(SearchService::class)->search($direction, ['term' => '']);
        $empty = app(SearchService::class)->search($direction, ['term' => 'introuvable']);

        $this->assertFalse($initial['has_query']);
        $this->assertStringContainsString('deux caractères', $initial['empty_message']);

        $this->assertTrue($empty['has_query']);
        $this->assertSame('Aucun résultat pour « introuvable ».', $empty['empty_message']);
        $this->assertNotSame($initial['empty_message'], $empty['empty_message']);
    }

    /** Un terme trop court ne lance aucune requête : il ramènerait tout le corpus. */
    public function test_a_single_character_term_runs_no_query(): void
    {
        $direction = $this->userWithRole('direction');
        Person::factory()->create(['full_name' => 'Aïcha']);

        $results = app(SearchService::class)->search($direction, ['term' => 'A']);

        $this->assertFalse($results['has_query']);
        $this->assertSame([], $results['groups']);
    }

    /** Les jokers SQL saisis par l'utilisateur sont échappés, jamais interprétés. */
    public function test_sql_wildcards_in_the_term_are_escaped(): void
    {
        $direction = $this->userWithRole('direction');
        Person::factory()->create(['full_name' => 'Ali Bello']);

        $results = app(SearchService::class)->search($direction, ['term' => '%%']);

        $this->assertSame(0, $results['total'], 'Un « % » saisi cherche un pourcent, il ne sélectionne pas tout.');
    }

    /** AC 6 — la page répond et se rend pour tout rôle autorisé. */
    public function test_ac_6_the_search_page_renders_for_every_authorised_role(): void
    {
        foreach (['direction', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $this->actingAs($this->userWithRole($role))
                ->get('/recherche?q=test')
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('Platform/Search')->has('results'));
        }
    }

    /** AC 22 — `super_admin` n'a aucune permission métier : la recherche lui est refusée. */
    public function test_super_admin_is_refused_access_to_search(): void
    {
        session()->flush();
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/recherche?q=test')
            ->assertForbidden();
    }

    /** Une plage de dates inversée est refusée par la Form Request, pas silencieusement ignorée. */
    public function test_an_inverted_date_range_is_rejected(): void
    {
        $this->actingAs($this->userWithRole('direction'))
            ->get('/recherche?q=test&from=2026-08-31&to=2026-08-01')
            ->assertSessionHasErrors('to');
    }

    /** AC 4 — le nombre de requêtes ne dépend pas du volume : la recherche reste bornée. */
    public function test_ac_4_search_cost_does_not_grow_with_the_dataset(): void
    {
        $direction = $this->userWithRole('direction');
        Person::factory()->count(40)->create(['full_name' => 'Volume Test']);
        Project::factory()->count(40)->create(['name' => 'Volume Test', 'status' => ProjectStatus::Actif->value]);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $results = app(SearchService::class)->search($direction, ['term' => 'Volume Test']);

        // Deux requêtes par type au plus — un compte et une page de résultats.
        $this->assertLessThanOrEqual(12, $queries, "La recherche a coûté {$queries} requêtes.");
        $this->assertSame(80, $results['total']);
        // Le compteur reste exact même si la page est tronquée.
        $this->assertSame(SearchService::PER_TYPE_LIMIT, count($results['groups'][0]['items']));
        $this->assertTrue($results['groups'][0]['truncated']);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->active()->withRole($role)->create();
    }
}
