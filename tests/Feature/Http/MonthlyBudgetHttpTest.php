<?php

namespace Tests\Feature\Http;

use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\MonthlyBudget;
use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Tests\Support\IdentityTestCase;

class MonthlyBudgetHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_finance_can_store_budget_and_employee_cannot_open_global_budget(): void
    {
        $category = ExpenseCategory::factory()->create();
        $finance = $this->roleUser('finance');
        $this->actingAs($finance)->post(route('monthly-budgets.store'), [
            'category_id' => $category->getKey(), 'month' => '2026-07', 'budget_amount' => 250_000,
        ])->assertRedirect(route('monthly-budgets.index', ['month' => '2026-07']));
        $budget = MonthlyBudget::query()->where('category_id', $category->getKey())->firstOrFail();
        $this->assertSame('2026-07-01', $budget->month->toDateString());
        $this->assertSame(250_000, $budget->budget_amount);

        session()->flush();
        Auth::forgetGuards();
        $this->actingAs($this->roleUser('employe'))->get(route('monthly-budgets.index'))->assertForbidden();
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test budgets story 8.1');

        return $user;
    }
}
