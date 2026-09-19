<?php

namespace Tests\Feature\Http;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $direction;

    private User $finance;

    private User $tuteur;

    private User $employe;

    private User $stagiaire;

    private User $superAdmin;

    private ExpenseCategory $category;

    private Expense $ownExpense;

    private Expense $otherExpense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->direction = User::factory()->active()->withRole('direction')->create();
        $this->finance = User::factory()->active()->withRole('finance')->create();
        $this->tuteur = User::factory()->active()->withRole('tuteur')->create();
        $this->employe = User::factory()->active()->withRole('employe')->create();
        $this->stagiaire = User::factory()->active()->withRole('stagiaire')->create();
        $this->superAdmin = User::factory()->active()->withRole('super_admin')->create();

        $this->category = ExpenseCategory::factory()->create();

        $this->ownExpense = Expense::factory()
            ->for($this->employe, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create();

        $this->otherExpense = Expense::factory()
            ->for($this->tuteur, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create();
    }

    #[Test]
    public function direction_can_view_all_expenses(): void
    {
        $this->actingAs($this->direction)
            ->get('/depenses')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page): Assert => $page->has('expenses', 2));
    }

    #[Test]
    public function finance_can_view_all_expenses(): void
    {
        $this->actingAs($this->finance)
            ->get('/depenses')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page): Assert => $page->has('expenses', 2));
    }

    #[Test]
    public function requester_can_view_only_own_expenses(): void
    {
        $this->actingAs($this->employe)
            ->get('/depenses')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('expenses', 1)
                ->where('expenses.0.id', $this->ownExpense->id));
    }

    #[Test]
    public function stagiaire_can_view_own_expenses(): void
    {
        $stagiaireExpense = Expense::factory()
            ->for($this->stagiaire, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create();

        $this->actingAs($this->stagiaire)
            ->get('/depenses')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('expenses', 1)
                ->where('expenses.0.id', $stagiaireExpense->id));
    }

    #[Test]
    public function super_admin_cannot_view_business_expenses(): void
    {
        // AC 3: super_admin has no business permission, but can view their own expenses
        $superAdminExpense = Expense::factory()
            ->for($this->superAdmin, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create();

        $this->actingAs($this->superAdmin)
            ->get('/depenses')
            ->assertStatus(403);

        $this->assertDatabaseHas('expenses', ['id' => $superAdminExpense->getKey()]);
    }

    #[Test]
    public function all_authenticated_users_can_access_create_form(): void
    {
        // AC 3: All authenticated users can create
        foreach ([$this->direction, $this->finance, $this->tuteur, $this->employe, $this->stagiaire, $this->superAdmin] as $user) {
            $this->flushSession();
            Auth::forgetGuards();
            $this->actingAs($user)
                ->get('/depenses/create')
                ->assertStatus(200);
        }
    }

    #[Test]
    public function requester_can_update_own_pending_expense(): void
    {
        $response = $this->actingAs($this->employe)
            ->patch("/depenses/{$this->ownExpense->id}", [
                'reason' => 'Updated reason',
            ])
            ->assertStatus(302);
    }

    #[Test]
    public function requester_cannot_update_others_expense(): void
    {
        $this->actingAs($this->employe)
            ->patch("/depenses/{$this->otherExpense->id}", [
                'reason' => 'Updated reason',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function requester_can_cancel_own_pending_expense(): void
    {
        $this->actingAs($this->employe)
            ->patch("/depenses/{$this->ownExpense->id}/annuler", [
                'cancel_reason' => 'No longer needed',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('expenses', [
            'id' => $this->ownExpense->id,
            'state' => ExpenseState::Annulee->value,
            'cancel_reason' => 'No longer needed',
        ]);
    }

    #[Test]
    public function direction_can_cancel_any_pending_expense(): void
    {
        $this->actingAs($this->direction)
            ->patch("/depenses/{$this->ownExpense->id}/annuler", [
                'cancel_reason' => 'Budget issue',
            ])
            ->assertStatus(302);

        $this->assertDatabaseHas('expenses', [
            'id' => $this->ownExpense->id,
            'state' => ExpenseState::Annulee->value,
        ]);
    }

    #[Test]
    public function approval_routes_now_exist_but_remain_unavailable_without_two_direction_accounts(): void
    {
        $this->actingAs($this->direction)
            ->patch("/depenses/{$this->ownExpense->id}/approuver")
            ->assertStatus(302)
            ->assertSessionHasErrors('approval');

        $this->actingAs($this->direction)
            ->patch("/depenses/{$this->ownExpense->id}/refuser", ['reason' => 'Motif de test.'])
            ->assertStatus(302)
            ->assertSessionHasErrors('approval');

        $this->assertEquals(ExpenseState::Demandee->value, $this->ownExpense->refresh()->state->value);
    }

    /**
     * AC 2 de la story 4.4 — le paiement est un **cycle distinct de l'approbation**.
     *
     * L'assertion d'origine vérifiait qu'aucune colonne de paiement n'existait encore, la story 8.6
     * n'étant pas livrée. Elle l'est désormais : `paid_at`, `paid_on` et les autres colonnes de
     * règlement sont là, légitimement. La story 10.1 corrige donc l'assertion **sans affaiblir le
     * critère d'origine** — ce que 4.4 protégeait n'était pas l'absence de colonnes, c'était que le
     * paiement ne double pas la machine à états de l'approbation.
     *
     * Ce qui reste vrai, et que ce test continue de garantir : il n'existe **pas** de colonne
     * `payment_state` concurrente de `state`. Un règlement se lit dans `state` et dans les colonnes
     * de paiement, jamais dans une seconde machine à états qui pourrait diverger de la première.
     */
    #[Test]
    public function no_payment_state_exists_in_44(): void
    {
        $this->assertFalse(
            \Schema::hasColumn('expenses', 'payment_state'),
            'Le paiement ne doit jamais introduire une seconde machine à états à côté de `state`.',
        );

        $this->assertFalse(
            \Schema::hasColumn('expenses', 'paid_amount'),
            'Le montant payé reste `requested_amount` : un second montant pourrait diverger du premier.',
        );

        // Constat explicite de l'évolution apportée par 8.6, pour que la correction de cette
        // assertion reste traçable plutôt que silencieuse.
        $this->assertTrue(
            \Schema::hasColumn('expenses', 'paid_at'),
            'La story 8.6 a livré les colonnes de règlement : leur absence signalerait une régression.',
        );
    }

    #[Test]
    public function cannot_delete_expense(): void
    {
        // AC 5: No physical deletion, only cancellation with reason
        $this->actingAs($this->employe)
            ->delete("/depenses/{$this->ownExpense->id}")
            ->assertStatus(405); // No destructive route exists

        // Verify expense still exists
        $this->assertDatabaseHas('expenses', [
            'id' => $this->ownExpense->id,
        ]);
    }
}
