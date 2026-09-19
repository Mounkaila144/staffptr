<?php

namespace Tests\Feature\Http;

use App\Enums\ExpenseState;
use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class ExpensePaymentHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_35_finance_can_pay_approved_expense_but_direction_cannot(): void
    {
        $expense = $this->approvedExpense();
        $payload = [
            'account_id' => Account::factory()->create()->getKey(), 'paid_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'),
            'payment_mode' => 'especes', 'payment_reference' => null, 'project_id' => null, 'contract_id' => null,
            'attachment_ulid' => null, 'is_advance_reimbursement' => false, 'payment_idempotency_key' => (string) Str::ulid(),
        ];

        $this->actingAs($this->userWithRole('direction'))->post(route('expense-payments.pay', $expense), $payload)->assertForbidden();
        $this->resetAuthentication();
        $this->actingAs($this->userWithRole('finance'))->post(route('expense-payments.pay', $expense), $payload)->assertRedirect(route('expense-payments.index'));
        $this->assertSame(ExpenseState::Payee, $expense->refresh()->state);
    }

    public function test_only_finance_reaches_payment_register(): void
    {
        $this->actingAs($this->userWithRole('finance'))->get(route('expense-payments.index'))->assertOk();
        $this->resetAuthentication();
        foreach (['super_admin', 'direction', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('expense-payments.index'))->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_ac_38_40_41_page_exposes_missing_receipts_advance_and_counter_entry_contracts(): void
    {
        $finance = $this->userWithRole('finance');
        $this->actingAs($finance)->get(route('expense-payments.index'))->assertOk()->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Finance/ExpensePayments/Index')->has('expenses')->has('missing_attachments')->has('attachment'),
        );
        $source = (string) file_get_contents(resource_path('js/Pages/Finance/ExpensePayments/Index.vue'));
        $this->assertStringContainsString('Paiements sans justificatif', $source);
        $this->assertStringContainsString('Remboursement d’une avance personnelle', $source);
        $this->assertStringContainsString('Annuler par contre-écriture', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    private function approvedExpense(): Expense
    {
        $expense = Expense::factory()->create(['state' => ExpenseState::Approuvee->value]);
        foreach ([User::factory()->active()->create(), User::factory()->active()->create()] as $approver) {
            ExpenseApproval::query()->create(['expense_id' => $expense->getKey(), 'approver_id' => $approver->getKey(), 'decision' => ExpenseApproval::DECISION_APPROVE, 'decided_at' => now('UTC')]);
        }

        return $expense;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test paiement dépense story 8.1');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
