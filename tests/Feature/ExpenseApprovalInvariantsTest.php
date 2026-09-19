<?php

namespace Tests\Feature;

use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Services\Platform\Invariants\ApprovedExpenseHasTwoApprovalsInvariant;
use App\Services\Platform\Invariants\ExpenseApproverCountInvariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExpenseApprovalInvariantsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ac_10_exactly_two_expense_approver_accounts_are_required(): void
    {
        $first = User::factory()->active()->withRole('direction')->create();
        User::factory()->active()->withRole('direction')->create();
        $invariant = app(ExpenseApproverCountInvariant::class);

        $this->assertTrue($invariant->check()->passed);

        $first->removeRole('direction');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $result = $invariant->check();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('1 compte', $result->observed);
    }

    #[Test]
    public function ac_10_forged_approved_expense_with_one_distinct_approval_is_detected(): void
    {
        $first = User::factory()->active()->withRole('direction')->create();
        $second = User::factory()->active()->withRole('direction')->create();
        $requester = User::factory()->active()->withRole('employe')->create();
        $expense = Expense::factory()
            ->approved()
            ->for($requester, 'requester')
            ->for(ExpenseCategory::factory(), 'category')
            ->create();
        ExpenseApproval::factory()->approved()->create([
            'expense_id' => $expense->getKey(),
            'approver_id' => $first->getKey(),
        ]);
        $invariant = app(ApprovedExpenseHasTwoApprovalsInvariant::class);

        $result = $invariant->check();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString("#{$expense->getKey()}", $result->observed);

        ExpenseApproval::factory()->approved()->create([
            'expense_id' => $expense->getKey(),
            'approver_id' => $second->getKey(),
        ]);

        $this->assertTrue($invariant->check()->passed);
    }
}
