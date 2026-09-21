<?php

namespace Tests\Feature\Http;

use App\Enums\CapitalContributionState;
use App\Models\Finance\CapitalContribution;
use App\Models\Identity\User;
use App\Services\Finance\CapitalContributionService;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

/**
 * Écran du registre des parts de contribution — story 12.1, AC 16 à 19.
 */
class ContributionShareHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->travelTo(now('UTC')->setDate(2026, 10, 15)->setTime(9, 0));
    }

    public function test_ac_16_17_and_19_the_screen_shows_totals_percentages_and_the_detail_of_each_line(): void
    {
        $first = $this->userWithRole('direction');
        $second = $this->userWithRole('direction');
        $service = app(CapitalContributionService::class);
        $service->approve($service->record(1_000_000, 'Apport initial.', (string) Str::ulid(), $first), $second);

        $this->actingAs($first)->get(route('contribution-shares.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Finance/ContributionShares/Index')
                ->has('register.directors', 2)
                ->where('register.directors.0.shares', 2_000_000)
                ->where('register.directors.0.percentage_label', '100,00 %')
                ->has('register.directors.0.entries', 1)
                ->where('register.directors.0.entries.0.origin_label', 'Apport d’argent personnel')
                ->where('register.directors.0.entries.0.vintage_label', 'Année 1')
                ->where('register.directors.0.entries.0.coefficient_label', '× 2')
                ->where('register.directors.0.entries.0.issued_shares', 2_000_000)
                ->has('register.directors.0.timeline')
                ->has('capitalContributions', 1)
                ->where('canContribute', true));
    }

    /** AC 16 — le registre vide dit ce qui est vide et pourquoi c'est normal (SOC-06). */
    public function test_ac_16_an_empty_register_still_lists_the_directors_with_no_shares(): void
    {
        $director = $this->userWithRole('direction');

        $this->actingAs($director)->get(route('contribution-shares.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('register.total_shares', 0)
                ->has('register.directors', 1)
                ->where('register.directors.0.shares', 0)
                ->where('register.directors.0.percentage_label', '—')
                ->has('register.directors.0.entries', 0)
                ->has('capitalContributions', 0));
    }

    /** AC 18 — aucun autre rôle n'accède au registre, y compris par URL directe. */
    public function test_ac_18_every_role_other_than_direction_is_refused_on_each_route(): void
    {
        $contribution = CapitalContribution::factory()->create([
            'contributor_id' => $this->userWithRole('direction')->getKey(),
        ]);

        foreach (['super_admin', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            // Changer de compte impose de vider la session : `AuthenticateSession` déconnecte
            // sinon, et le refus attendu se transformerait en redirection vers la connexion.
            $this->flushSession();
            Auth::forgetGuards();
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('contribution-shares.index'))->assertForbidden();
            $this->actingAs($user)->post(route('capital-contributions.store'), [
                'contribution_amount' => 100_000, 'purpose' => 'Tentative.', 'idempotency_key' => (string) Str::ulid(),
            ])->assertForbidden();
            $this->actingAs($user)->patch(route('capital-contributions.approve', $contribution))->assertForbidden();
            $this->actingAs($user)->patch(route('capital-contributions.refuse', $contribution), [
                'refusal_reason' => 'Tentative.',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('capital_contributions', 1);
        $this->assertDatabaseCount('contribution_shares', 0);
    }

    public function test_ac_7_and_8_a_director_records_an_apport_that_waits_for_the_second_director(): void
    {
        $first = $this->userWithRole('direction');
        $second = $this->userWithRole('direction');

        $this->actingAs($first)->post(route('capital-contributions.store'), [
            'contribution_amount' => 2_000_000,
            'purpose' => 'Renforcement de la trésorerie de démarrage.',
            'idempotency_key' => (string) Str::ulid(),
        ])->assertRedirect(route('contribution-shares.index'));

        $contribution = CapitalContribution::query()->firstOrFail();
        $this->assertSame(CapitalContributionState::EnAttente, $contribution->state);
        $this->assertDatabaseCount('contribution_shares', 0);

        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($second)->patch(route('capital-contributions.approve', $contribution))
            ->assertRedirect(route('contribution-shares.index'));

        $this->assertSame(CapitalContributionState::Approuve, $contribution->refresh()->state);
        $this->assertDatabaseHas('contribution_shares', ['holder_id' => $first->getKey(), 'issued_shares' => 4_000_000]);
    }

    /** AC 9 — l'auteur d'un apport ne peut pas le trancher, même en forçant l'URL. */
    public function test_ac_9_a_director_cannot_decide_on_their_own_capital_contribution(): void
    {
        $director = $this->userWithRole('direction');
        $contribution = app(CapitalContributionService::class)
            ->record(500_000, 'Apport propre.', (string) Str::ulid(), $director);

        $this->actingAs($director)->patch(route('capital-contributions.approve', $contribution))->assertForbidden();
        $this->actingAs($director)->patch(route('capital-contributions.refuse', $contribution), [
            'refusal_reason' => 'Je me refuse moi-même.',
        ])->assertForbidden();

        $this->assertSame(CapitalContributionState::EnAttente, $contribution->refresh()->state);
        $this->assertDatabaseCount('contribution_shares', 0);
    }

    public function test_ac_11_a_refusal_without_a_reason_is_rejected_by_the_form_request(): void
    {
        $first = $this->userWithRole('direction');
        $second = $this->userWithRole('direction');
        $contribution = app(CapitalContributionService::class)
            ->record(500_000, 'Avance de trésorerie.', (string) Str::ulid(), $first);

        $this->actingAs($second)->patch(route('capital-contributions.refuse', $contribution), [])
            ->assertSessionHasErrors(['refusal_reason']);

        $this->assertSame(CapitalContributionState::EnAttente, $contribution->refresh()->state);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP registre des parts 12.1');

        return $user;
    }
}
