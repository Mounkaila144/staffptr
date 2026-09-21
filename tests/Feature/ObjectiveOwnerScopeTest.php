<?php

namespace Tests\Feature;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Support\Work\AssignableOwners;
use Tests\Support\IdentityTestCase;

/**
 * Périmètre du responsable d'un objectif.
 *
 * Tous les rôles portent `objectif_individuel.gerer` : sans règle explicite,
 * `exists:users,id` laissait n'importe quel compte inscrire du travail au nom
 * de n'importe qui d'autre. La liste déroulante du formulaire lit la même
 * source que la validation — mais c'est la validation qui protège.
 */
class ObjectiveOwnerScopeTest extends IdentityTestCase
{
    /** @return array<string, mixed> */
    private function payload(int $ownerId): array
    {
        return [
            'user_id' => $ownerId,
            'title' => 'Réduire le délai de traitement',
            'description' => 'Ramener le délai moyen sous cinq jours ouvrés.',
            'indicator' => 'Délai moyen de traitement',
            'target_value' => '5 jours',
            'expected_evidence' => 'Extraction mensuelle des délais.',
            'due_date' => now()->addDays(20)->format('Y-m-d'),
            'priority' => 'normale',
        ];
    }

    public function test_an_employee_may_only_take_an_objective_for_themselves(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();

        $this->assertSame([(int) $employee->getKey()], AssignableOwners::idsFor($employee));

        $this->actingAs($employee)
            ->post('/objectifs', $this->payload((int) $employee->getKey()))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Objective::query()->where('user_id', $employee->getKey())->count());
    }

    /**
     * Le cas qui était ouvert : un compte sans autorité désignait un tiers.
     */
    public function test_an_employee_cannot_assign_an_objective_to_a_colleague(): void
    {
        $employee = User::factory()->active()->withRole('employe')->create();
        $colleague = User::factory()->active()->withRole('employe')->create();

        $this->actingAs($employee)
            ->post('/objectifs', $this->payload((int) $colleague->getKey()))
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, Objective::query()->where('user_id', $colleague->getKey())->count());
    }

    public function test_a_tutor_may_assign_to_their_team_but_not_beyond(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $member = User::factory()->active()->withRole('stagiaire')->create(['manager_id' => $tutor->getKey()]);
        $outsider = User::factory()->active()->withRole('employe')->create();

        $assignable = AssignableOwners::idsFor($tutor);
        $this->assertContains((int) $tutor->getKey(), $assignable);
        $this->assertContains((int) $member->getKey(), $assignable);
        $this->assertNotContains((int) $outsider->getKey(), $assignable);

        $this->actingAs($tutor)
            ->post('/objectifs', $this->payload((int) $member->getKey()))
            ->assertSessionHasNoErrors();

        $this->actingAs($tutor)
            ->post('/objectifs', $this->payload((int) $outsider->getKey()))
            ->assertSessionHasErrors('user_id');
    }

    public function test_direction_may_assign_to_anyone_active(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $anyone = User::factory()->active()->withRole('employe')->create();

        $this->assertContains((int) $anyone->getKey(), AssignableOwners::idsFor($direction));

        $this->actingAs($direction)
            ->post('/objectifs', $this->payload((int) $anyone->getKey()))
            ->assertSessionHasNoErrors();
    }

    /**
     * Un compte encore « invité » doit pouvoir porter un objectif.
     *
     * L'activation d'un stagiaire exige trois objectifs enregistrés à son nom, et son compte
     * n'est actif qu'*après* cette activation. Filtrer sur le seul état « actif » refermait le
     * parcours sur lui-même : la liste ne proposait personne et la validation refusait tout.
     */
    public function test_direction_may_assign_to_an_invited_account_awaiting_activation(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $intern = User::factory()->withRole('stagiaire')->create(['state' => UserState::Invite]);

        $this->assertContains((int) $intern->getKey(), AssignableOwners::idsFor($direction));
        $this->assertContains((int) $intern->getKey(), array_column(AssignableOwners::optionsFor($direction), 'id'));

        $this->actingAs($direction)
            ->post('/objectifs', $this->payload((int) $intern->getKey()))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Objective::query()->where('user_id', $intern->getKey())->count());
    }

    /** Un compte suspendu, lui, reste hors périmètre : rien ne s'inscrit plus à son nom. */
    public function test_a_suspended_account_can_no_longer_be_designated(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        $suspended = User::factory()->suspended()->withRole('employe')->create();

        $this->assertNotContains((int) $suspended->getKey(), AssignableOwners::idsFor($direction));

        $this->actingAs($direction)
            ->post('/objectifs', $this->payload((int) $suspended->getKey()))
            ->assertSessionHasErrors('user_id');
    }

    /**
     * Le formulaire ne doit proposer que ce que le serveur accepterait : une
     * liste déroulante qui offre un choix refusé ensuite est un piège.
     */
    public function test_the_form_offers_exactly_the_accepted_choices(): void
    {
        $tutor = User::factory()->active()->withRole('tuteur')->create();
        $member = User::factory()->active()->withRole('employe')->create(['manager_id' => $tutor->getKey()]);
        User::factory()->active()->withRole('employe')->create();

        $offered = array_column(AssignableOwners::optionsFor($tutor), 'id');
        sort($offered);
        $accepted = AssignableOwners::idsFor($tutor);
        sort($accepted);

        $this->assertSame($accepted, $offered);

        $this->actingAs($tutor)
            ->get('/objectifs')
            ->assertInertia(fn ($page) => $page
                ->has('assignableOwners', 2)
                ->where('assignableOwners.0.id', fn ($id): bool => in_array((int) $id, $accepted, true)));

        $this->assertContains((int) $member->getKey(), $accepted);
    }

    public function test_every_offered_option_carries_a_readable_name(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();

        foreach (AssignableOwners::optionsFor($direction) as $option) {
            $this->assertNotSame('', trim($option['name']), 'Une option sans nom rendrait le menu inutilisable.');
        }
    }
}
