<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Services\Finance\MonthlyBudgetService;
use Tests\Support\IdentityTestCase;

class MonthlyBudgetLifecycleTest extends IdentityTestCase
{
    public function test_ac_65_to_69_budget_comparison_is_audited_and_never_blocks_unbudgeted_expenses(): void
    {
        $category = ExpenseCategory::factory()->create(['name' => 'Transport terrain']);
        $other = ExpenseCategory::factory()->create(['name' => 'Imprévu sans budget']);
        Expense::factory()->create(['category_id' => $category, 'requested_amount' => 150_000, 'state' => ExpenseState::Payee->value, 'paid_on' => '2026-07-12']);
        Expense::factory()->create(['category_id' => $other, 'requested_amount' => 25_000, 'state' => ExpenseState::Payee->value, 'paid_on' => '2026-07-13']);
        $actor = User::factory()->active()->create();
        $service = app(MonthlyBudgetService::class);

        $before = $service->comparison('2026-07');
        $this->assertSame('Aucun budget défini pour juillet. Les dépenses restent possibles.', $before['empty_message']);
        $this->assertContains('Aucun budget — dépense autorisée', array_column($before['rows'], 'status_label'));

        $budget = $service->save((int) $category->getKey(), '2026-07', 100_000, $actor);
        $after = $service->comparison('2026-07');
        $row = collect($after['rows'])->firstWhere('category_id', $category->getKey());
        $this->assertSame(150_000, $row['actual_amount']);
        $this->assertSame(150, $row['actual_percentage']);
        $this->assertTrue($row['is_exceeded']);
        $this->assertSame('Budget dépassé', $row['status_label']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $budget::class, 'auditable_id' => $budget->getKey()]);
    }
}
