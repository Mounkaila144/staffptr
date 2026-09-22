<?php

namespace Tests\Feature\Http;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Services\Identity\RoleAssignmentService;
use App\Support\Work\AssignablePeople;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

/**
 * Aucun formulaire ne demande d'écrire un identifiant à la main.
 *
 * Personne ne connaît par cœur l'identifiant numérique de ses collègues. Un champ qui le réclame
 * est soit deviné, soit laissé de côté, soit rempli faux sans que rien ne le signale : la
 * validation se contente d'un `exists`, et une tâche part alors chez quelqu'un d'autre.
 *
 * Ce test tient les deux bouts — la liste part bien du serveur, et l'écran ne contient plus de
 * champ de saisie libre pour un identifiant.
 */
class PersonPickerContractTest extends IdentityTestCase
{
    /** Écrans de saisie qui désignaient une personne ou un objet par son identifiant. */
    private const PAGES = [
        'Work/Projects/Index',
        'Work/Projects/Show',
        'Work/Tasks/Index',
        'Work/Deliverables/Index',
        'Work/CompanyPriorities/Index',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_no_screen_asks_for_an_identifier_to_be_typed_by_hand(): void
    {
        foreach (self::PAGES as $page) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$page}.vue"));

            $this->assertStringNotContainsStringIgnoringCase(
                'identifiant du',
                $source,
                "{$page} ne doit plus réclamer un identifiant saisi à la main.",
            );
            $this->assertStringNotContainsString(
                '(identifiant)',
                $source,
                "{$page} ne doit plus réclamer un identifiant saisi à la main.",
            );
        }
    }

    /**
     * Un champ `*_id` se choisit dans une liste. La seule exception admise est un montant, qui
     * n'est pas un identifiant malgré son clavier numérique.
     */
    public function test_no_identifier_field_is_bound_to_a_free_text_input(): void
    {
        foreach (self::PAGES as $page) {
            $source = (string) file_get_contents(resource_path("js/Pages/{$page}.vue"));
            $offenders = [];

            foreach (explode('<input', $source) as $index => $fragment) {
                if ($index === 0) {
                    continue;
                }

                $field = substr($fragment, 0, (int) strpos($fragment.'>', '>'));

                if (preg_match('/v-model="[^"]*_id"/', $field) === 1) {
                    $offenders[] = trim(preg_replace('/\s+/', ' ', $field) ?? '');
                }
            }

            $this->assertSame([], $offenders, "{$page} lie encore un identifiant à une saisie libre : ".implode(' | ', $offenders));
        }
    }

    public function test_the_project_screens_carry_the_directory_the_dropdown_needs(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');

        $this->actingAs($direction)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('assignablePeople')
                ->where('assignablePeople', fn (Collection $people): bool => $people
                    ->pluck('id')
                    ->contains((int) $employee->getKey())));

        $project = Project::factory()->create(['manager_id' => $direction->getKey()]);
        $this->actingAs($direction)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('assignablePeople'));
    }

    public function test_the_task_screen_carries_every_list_its_form_needs(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('assignablePeople')
                ->has('projects')
                ->has('objectives')
                ->has('parentTasks'));
    }

    /**
     * L'annuaire retient les comptes actifs **et invités**, jamais les comptes fermés.
     *
     * Un stagiaire dont le compte attend l'activation se voit déjà confier des objectifs ; rien
     * ne justifie qu'un projet ou une tâche lui soit refusé. Un compte suspendu, lui, n'a plus à
     * recevoir de travail.
     */
    public function test_the_directory_holds_active_and_invited_accounts_only(): void
    {
        $active = $this->userWithRole('employe');
        $invited = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);
        $suspended = User::factory()->suspended()->withRole('employe')->create();

        $ids = array_column(AssignablePeople::options(), 'id');

        $this->assertContains((int) $active->getKey(), $ids);
        $this->assertContains((int) $invited->getKey(), $ids);
        $this->assertNotContains((int) $suspended->getKey(), $ids);
    }

    public function test_every_option_carries_a_readable_name_sorted_alphabetically(): void
    {
        $this->userWithRole('employe');
        $this->userWithRole('tuteur');
        $options = AssignablePeople::options();
        $names = array_column($options, 'name');
        $sorted = $names;
        sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);

        $this->assertNotSame([], $options);
        $this->assertSame($sorted, $names, 'Une liste non triée devient inutilisable dès qu’elle dépasse quelques lignes.');

        foreach ($names as $name) {
            $this->assertNotSame('', trim($name), 'Une option sans nom rendrait le menu inutilisable.');
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test des listes de personnes');

        return $user;
    }
}
