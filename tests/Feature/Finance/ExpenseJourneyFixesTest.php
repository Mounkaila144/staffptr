<?php

namespace Tests\Feature\Finance;

use App\Enums\ExpenseState;
use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Finance\ExpensePaymentService;
use App\Services\Finance\FinancialDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

/**
 * Défauts relevés en parcourant le circuit complet d'une dépense dans le navigateur.
 */
class ExpenseJourneyFixesTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_pending_expenses_block_never_links_finance_to_the_direction_only_approval_queue(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $direction = User::factory()->active()->withRole('direction')->create();
        $dashboard = app(FinancialDashboardService::class);

        $this->assertSame('/depenses?state=demandee', $dashboard->blocks($finance)['pending_expenses']['url']);
        $this->assertSame('/depenses/approbations', $dashboard->blocks($direction)['pending_expenses']['url']);
        $this->actingAs($finance)->get('/depenses?state=demandee')->assertOk();
    }

    public function test_a_colleague_cannot_cancel_someone_elses_pending_expense(): void
    {
        $expense = Expense::factory()->for(User::factory()->active()->withRole('employe')->create(), 'requester')->requested()->create();

        $this->actingAs(User::factory()->active()->withRole('employe')->create())
            ->patch("/depenses/{$expense->id}/annuler", ['cancel_reason' => 'Je ne suis pas le demandeur'])
            ->assertForbidden();
        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);
    }

    public function test_finance_cannot_cancel_someone_elses_pending_expense(): void
    {
        $expense = Expense::factory()->for(User::factory()->active()->withRole('employe')->create(), 'requester')->requested()->create();

        $this->actingAs(User::factory()->active()->withRole('finance')->create())
            ->patch("/depenses/{$expense->id}/annuler", ['cancel_reason' => 'La finance ne décide pas'])
            ->assertForbidden();
        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);
    }

    public function test_requester_sees_decisions_refusal_reason_and_local_time_on_the_detail_page(): void
    {
        $requester = User::factory()->active()->withRole('employe')->create();
        $approver = User::factory()->active()->withRole('direction')->create();
        $expense = Expense::factory()->for($requester, 'requester')->refused()->create([
            'created_at' => CarbonImmutable::parse('2026-10-31 14:06:00', 'UTC'),
        ]);
        ExpenseApproval::query()->create([
            'expense_id' => $expense->getKey(), 'approver_id' => $approver->getKey(),
            'decision' => ExpenseApproval::DECISION_REJECT, 'comment' => 'Hors budget ce mois-ci.',
            'decided_at' => CarbonImmutable::parse('2026-10-31 15:00:00', 'UTC'),
        ]);

        $this->actingAs($requester)->get("/depenses/{$expense->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('expense.created_at', '31/10/2026 15:06')
                ->where('expense.approvals.0.decision', ExpenseApproval::DECISION_REJECT)
                ->where('expense.approvals.0.comment', 'Hors budget ce mois-ci.')
                ->where('expense.approvals.0.decided_at', '31/10/2026 16:00')
                ->where('expense.payment', null));
    }

    public function test_register_offers_the_payment_screen_to_finance(): void
    {
        $this->actingAs(User::factory()->active()->withRole('finance')->create())->get('/depenses')
            ->assertInertia(fn (Assert $page): Assert => $page->where('can_pay', true));
    }

    public function test_register_does_not_offer_the_payment_screen_to_direction(): void
    {
        $this->actingAs(User::factory()->active()->withRole('direction')->create())->get('/depenses')
            ->assertInertia(fn (Assert $page): Assert => $page->where('can_pay', false));
    }

    public function test_finance_regularizes_a_payment_recorded_without_proof(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $expense = $this->paidExpenseWithoutProof($finance);
        $attachment = $this->stagedAttachment($finance);
        $this->assertCount(1, app(ExpensePaymentService::class)->forManagement()['missing_attachments']);

        $this->actingAs($finance)
            ->post("/finances/paiements-depenses/{$expense->id}/justificatif", ['attachment_ulid' => $attachment->ulid])
            ->assertRedirect('/finances/paiements-depenses')
            ->assertSessionHas('success', 'Le justificatif de paiement a été enregistré.');

        $this->assertSame($attachment->getKey(), $expense->refresh()->payment_attachment_id);
        $this->assertSame([], app(ExpensePaymentService::class)->forManagement()['missing_attachments']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Expense::class, 'auditable_id' => $expense->getKey(), 'action' => 'expense_payment_proof_attached']);
        // Déjà régularisé : une seconde pièce ne remplace pas la première.
        $this->assertFalse($finance->can('attachPaymentProof', $expense));
    }

    public function test_regularization_requires_a_file(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $expense = $this->paidExpenseWithoutProof($finance);

        $this->actingAs($finance)
            ->post("/finances/paiements-depenses/{$expense->id}/justificatif", [])
            ->assertSessionHasErrors(['attachment_ulid' => 'Ajoutez le justificatif de paiement avant de l’enregistrer.']);
    }

    public function test_direction_cannot_regularize_a_payment(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $expense = $this->paidExpenseWithoutProof($finance);

        $this->actingAs(User::factory()->active()->withRole('direction')->create())
            ->post("/finances/paiements-depenses/{$expense->id}/justificatif", ['attachment_ulid' => $this->stagedAttachment($finance)->ulid])
            ->assertForbidden();
    }

    public function test_payment_list_shows_the_payment_time_in_french_format(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $this->paidExpenseWithoutProof($finance, '2026-10-31T15:13');

        $this->assertSame('31/10/2026 15:13', app(ExpensePaymentService::class)->forManagement()['expenses'][0]['paid_at']);
    }

    public function test_category_names_are_unique_regardless_of_case(): void
    {
        $direction = User::factory()->active()->withRole('direction')->create();
        ExpenseCategory::factory()->create(['name' => 'Salaires']);

        $this->actingAs($direction)
            ->post('/categories-depense', ['name' => 'salaires', 'is_essential' => false])
            ->assertSessionHasErrors(['name' => 'Une catégorie porte déjà ce nom.']);
        $this->assertSame(1, ExpenseCategory::query()->whereRaw('LOWER(name) = ?', ['salaires'])->count());
    }

    private function stagedAttachment(User $owner): Attachment
    {
        return Attachment::factory()->create([
            'attachable_type' => $owner->person->getMorphClass(), 'attachable_id' => $owner->person_id, 'uploaded_by' => $owner->getKey(),
        ]);
    }

    private function paidExpenseWithoutProof(User $finance, ?string $paidAt = null): Expense
    {
        $expense = Expense::factory()->create(['state' => ExpenseState::Approuvee->value]);
        foreach ([User::factory()->active()->create(), User::factory()->active()->create()] as $approver) {
            ExpenseApproval::query()->create([
                'expense_id' => $expense->getKey(), 'approver_id' => $approver->getKey(),
                'decision' => ExpenseApproval::DECISION_APPROVE, 'decided_at' => now('UTC'),
            ]);
        }

        return app(ExpensePaymentService::class)->pay($expense, [
            'account_id' => (int) Account::factory()->create()->getKey(),
            'paid_at' => $paidAt ?? now('Africa/Niamey')->format('Y-m-d\\TH:i'),
            'payment_mode' => 'especes', 'payment_reference' => null, 'project_id' => null, 'contract_id' => null,
            'attachment_ulid' => null, 'is_advance_reimbursement' => false, 'payment_idempotency_key' => (string) Str::ulid(),
        ], $finance);
    }
}
