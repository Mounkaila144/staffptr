<?php

namespace Tests\Feature\Http;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Services\Finance\ExpenseApprovalService;
use App\Services\Identity\ExpenseApprovalReadiness;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class ExpenseApprovalHttpTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private User $firstDirection;

    private User $secondDirection;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firstDirection = User::factory()->active()->withRole('direction')->create();
        $this->secondDirection = User::factory()->active()->withRole('direction')->create();
        $this->category = ExpenseCategory::factory()->create();
    }

    #[Test]
    public function ac_1_real_routes_record_two_distinct_direction_approvals(): void
    {
        $expense = $this->requestedExpense();

        $this->actingAs($this->firstDirection)
            ->patch(route('expenses.approve', $expense))
            ->assertRedirect(route('expenses.approvals.index'));
        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);

        $this->flushSession();
        Auth::forgetGuards();
        $this->actingAs($this->secondDirection)
            ->patch(route('expenses.approve', $expense))
            ->assertRedirect(route('expenses.approvals.index'));
        $this->assertSame(ExpenseState::Approuvee, $expense->refresh()->state);
    }

    #[Test]
    public function ac_3_form_request_and_policy_block_the_direction_requester_with_exact_message(): void
    {
        $expense = $this->requestedExpense($this->firstDirection);

        $this->actingAs($this->firstDirection)
            ->from(route('expenses.approvals.index'))
            ->patch(route('expenses.approve', $expense))
            ->assertRedirect(route('expenses.approvals.index'))
            ->assertSessionHasErrors([
                'approval' => ExpenseApprovalService::REQUESTER_MESSAGE,
            ]);

        $this->assertFalse($this->firstDirection->can('approve', $expense));
        $this->assertDatabaseMissing('expense_approvals', ['expense_id' => $expense->getKey()]);
    }

    #[Test]
    public function ac_5_finance_and_all_non_direction_roles_receive_server_side_403(): void
    {
        $expense = $this->requestedExpense();

        foreach (['finance', 'tuteur', 'employe', 'stagiaire', 'super_admin'] as $role) {
            $user = User::factory()->active()->withRole($role)->create();
            $this->flushSession();
            Auth::forgetGuards();

            $this->actingAs($user)
                ->get(route('expenses.approvals.index'))
                ->assertForbidden();
            $this->actingAs($user)
                ->patch(route('expenses.approve', $expense))
                ->assertForbidden();
            $this->actingAs($user)
                ->patch(route('expenses.refuse', $expense), ['reason' => 'Décision test.'])
                ->assertForbidden();
        }

        $this->assertFalse($this->secondDirection->can('depense.payer'));
        $this->assertFalse($this->secondDirection->can('depense.approbation_derogatoire'));
    }

    #[Test]
    public function ac_6_refusal_endpoint_requires_a_reason_and_one_refusal_changes_state(): void
    {
        $expense = $this->requestedExpense();

        $this->actingAs($this->firstDirection)
            ->patch(route('expenses.refuse', $expense))
            ->assertSessionHasErrors('reason');
        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);

        $this->actingAs($this->firstDirection)
            ->patch(route('expenses.refuse', $expense), ['reason' => 'Le bénéfice attendu est insuffisant.'])
            ->assertRedirect(route('expenses.approvals.index'));
        $this->assertSame(ExpenseState::Refusee, $expense->refresh()->state);
    }

    #[Test]
    public function ac_9_screen_explains_when_two_direction_accounts_do_not_exist(): void
    {
        $this->secondDirection->removeRole('direction');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $expense = $this->requestedExpense();

        $this->actingAs($this->firstDirection)
            ->get(route('expenses.approvals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/Expenses/Approvals/Index')
                ->where('readiness.approval_available', false)
                ->where('readiness.approval_account_count', 1)
                ->where('readiness.message', ExpenseApprovalReadiness::UNAVAILABLE_MESSAGE)
                ->where('expenses.0.can_decide', false)
                ->where('expenses.0.state', ExpenseState::Demandee->value));

        $this->actingAs($this->firstDirection)
            ->patch(route('expenses.approve', $expense))
            ->assertSessionHasErrors([
                'approval' => ExpenseApprovalReadiness::UNAVAILABLE_MESSAGE,
            ]);
    }

    #[Test]
    public function approval_screen_displays_unambiguous_progress_and_money_format(): void
    {
        $expense = $this->requestedExpense(amount: 125_000);
        app(ExpenseApprovalService::class)->approve($expense, $this->firstDirection);

        $this->actingAs($this->secondDirection)
            ->get(route('expenses.approvals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Finance/Expenses/Approvals/Index')
                ->where('expenses.0.approval_count', 1)
                ->where('expenses.0.approval_progress', 'Demandée — 1 approbation sur deux')
                ->where('expenses.0.formatted_amount', $expense->formattedAmount())
                ->where('expenses.0.can_decide', true));
    }

    private function requestedExpense(?User $requester = null, int $amount = 50_000): Expense
    {
        return Expense::factory()
            ->for($requester ?? User::factory()->active()->withRole('employe'), 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create(['requested_amount' => $amount]);
    }
}
