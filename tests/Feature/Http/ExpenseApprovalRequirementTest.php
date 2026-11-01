<?php

namespace Tests\Feature\Http;

use App\Enums\ExpenseState;
use App\Enums\UserState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Finance\ExpenseApprovalService;
use App\Services\Platform\Invariants\ApprovedExpenseHasTwoApprovalsInvariant;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

/**
 * RM-09, décision du propriétaire du 2026-11-01 : approbations requises =
 * 2, sauf demande d'un dirigeant : min(2, autres dirigeants actifs), au moins 1.
 */
class ExpenseApprovalRequirementTest extends TestCase
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
    public function an_employee_request_still_needs_two_distinct_direction_approvals(): void
    {
        $expense = $this->requestedExpense(User::factory()->active()->withRole('employe')->create());

        $this->actingAs($this->firstDirection)
            ->get(route('expenses.approvals.show', $expense))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('expenses.0.approvals_required', 2)
                ->where('expenses.0.approval_progress', 'Demandée — 0 approbation sur 2')
                ->where('expenses.0.approval_rule', 'Deux approbations de deux dirigeants différents sont requises.'));

        $this->actingAs($this->firstDirection)->patch(route('expenses.approve', $expense))->assertRedirect();
        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);
        $this->assertNull($expense->approvals_required);

        $this->resetAuthentication();
        $this->actingAs($this->secondDirection)->patch(route('expenses.approve', $expense))->assertRedirect();
        $expense->refresh();
        $this->assertSame(ExpenseState::Approuvee, $expense->state);
        $this->assertSame(2, $expense->approvals_required);
    }

    #[Test]
    public function a_direction_request_with_two_direction_accounts_is_approved_by_the_other_one_alone(): void
    {
        $expense = $this->requestedExpense($this->firstDirection);

        $this->actingAs($this->firstDirection)
            ->get(route('expenses.show', $expense))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('expense.approvals_required', 1)
                ->where('expense.approval_progress', 'Demandée — 0 approbation sur 1')
                ->where('expense.approval_rule', "Demande d'un dirigeant : l'approbation de l'autre dirigeant suffit."));

        $this->resetAuthentication();
        $this->actingAs($this->secondDirection)->patch(route('expenses.approve', $expense))->assertRedirect();

        $expense->refresh();
        $this->assertSame(ExpenseState::Approuvee, $expense->state);
        $this->assertSame(1, $expense->approvals_required);

        $audit = AuditLog::query()
            ->where('auditable_type', $expense->getMorphClass())
            ->where('auditable_id', $expense->getKey())
            ->where('action', 'expense_approved')
            ->sole();
        $this->assertSame(ExpenseState::Approuvee->value, $audit->new_values['state']);
        $this->assertSame(1, $audit->new_values['approvals_required']);
        $this->assertStringContainsString('RM-09', (string) $audit->new_values['approval_rule']);
        $this->assertStringContainsString("l'approbation de l'autre dirigeant suffit", (string) $audit->new_values['approval_rule']);

        $this->assertTrue(app(ApprovedExpenseHasTwoApprovalsInvariant::class)->check()->passed);
    }

    #[Test]
    public function a_direction_requester_never_approves_their_own_expense(): void
    {
        $expense = $this->requestedExpense($this->firstDirection);

        $this->actingAs($this->firstDirection)
            ->from(route('expenses.approvals.index'))
            ->patch(route('expenses.approve', $expense))
            ->assertSessionHasErrors(['approval' => ExpenseApprovalService::REQUESTER_MESSAGE]);

        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);
        $this->assertDatabaseMissing('expense_approvals', ['expense_id' => $expense->getKey()]);
        $this->actingAs($this->firstDirection)
            ->get(route('expenses.approvals.index'))
            ->assertInertia(fn (Assert $page): Assert => $page->has('expenses', 0));
    }

    #[Test]
    public function an_inactive_direction_account_does_not_count_among_possible_approvers(): void
    {
        $thirdDirection = User::factory()->withRole('direction')->create(['state' => UserState::Suspendu]);
        $expense = $this->requestedExpense($this->firstDirection);

        $this->actingAs($this->secondDirection)
            ->get(route('expenses.approvals.show', $expense))
            ->assertInertia(fn (Assert $page): Assert => $page->where('expenses.0.approvals_required', 1));
        $this->assertNotNull($thirdDirection->getKey());
    }

    #[Test]
    public function a_suspended_director_never_lowers_an_employee_request_below_two_approvals(): void
    {
        $this->secondDirection->forceFill(['state' => UserState::Suspendu])->save();
        $expense = $this->requestedExpense(User::factory()->active()->withRole('employe')->create());

        $this->actingAs($this->firstDirection)
            ->get(route('expenses.approvals.show', $expense))
            ->assertInertia(fn (Assert $page): Assert => $page->where('expenses.0.approvals_required', 2));

        $this->actingAs($this->firstDirection)->patch(route('expenses.approve', $expense))->assertRedirect();
        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);
    }

    #[Test]
    public function the_invariant_still_flags_an_approved_expense_missing_its_frozen_approvals(): void
    {
        $expense = $this->requestedExpense(User::factory()->active()->withRole('employe')->create());
        $expense->forceFill(['state' => ExpenseState::Approuvee->value, 'approvals_required' => 2])->save();

        $result = app(ApprovedExpenseHasTwoApprovalsInvariant::class)->check();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('#'.$expense->getKey(), $result->observed);
    }

    private function requestedExpense(User $requester): Expense
    {
        return Expense::factory()
            ->for($requester, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create(['requested_amount' => 40_000]);
    }

    private function resetAuthentication(): void
    {
        $this->flushSession();
        Auth::forgetGuards();
    }
}
