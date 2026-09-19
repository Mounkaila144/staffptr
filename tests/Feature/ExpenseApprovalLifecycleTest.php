<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Finance\ExpenseApprovalService;
use App\Services\Identity\ExpenseApprovalReadiness;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class ExpenseApprovalLifecycleTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    private ExpenseApprovalService $service;

    private ExpenseCategory $category;

    private User $firstDirection;

    private User $secondDirection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ExpenseApprovalService::class);
        $this->category = ExpenseCategory::factory()->create();
        $this->firstDirection = User::factory()->active()->withRole('direction')->create();
        $this->secondDirection = User::factory()->active()->withRole('direction')->create();
    }

    #[Test]
    public function ac_1_expense_is_approved_only_after_two_distinct_direction_approvals(): void
    {
        $expense = $this->requestedExpense();

        $afterFirst = $this->service->approve($expense, $this->firstDirection);

        $this->assertSame(ExpenseState::Demandee, $afterFirst->state);
        $this->assertSame(1, $afterFirst->approvals()->where('decision', ExpenseApproval::DECISION_APPROVE)->count());

        $afterSecond = $this->service->approve($afterFirst, $this->secondDirection);

        $this->assertSame(ExpenseState::Approuvee, $afterSecond->state);
        $this->assertSame(2, $afterSecond->approvals()->distinct('approver_id')->count('approver_id'));
    }

    #[Test]
    public function ac_2_one_thousand_xof_still_requires_two_approvals_without_threshold(): void
    {
        $expense = $this->requestedExpense(1000);

        $this->service->approve($expense, $this->firstDirection);

        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);
        $this->service->approve($expense, $this->secondDirection);
        $this->assertSame(ExpenseState::Approuvee, $expense->refresh()->state);
    }

    #[Test]
    public function ac_3_direction_requester_never_approves_own_expense_with_exact_message(): void
    {
        $expense = $this->requestedExpense(requester: $this->firstDirection);

        try {
            $this->service->approve($expense, $this->firstDirection);
            $this->fail('Le demandeur direction ne doit jamais approuver sa propre dépense.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ExpenseApprovalService::REQUESTER_MESSAGE,
                $exception->errors()['approval'][0] ?? null,
            );
        }

        $this->assertDatabaseMissing('expense_approvals', ['expense_id' => $expense->getKey()]);
    }

    #[Test]
    public function ac_5_one_direction_account_cannot_create_an_exception_or_delegated_approval(): void
    {
        $this->secondDirection->removeRole('direction');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $expense = $this->requestedExpense();

        try {
            $this->service->approve($expense, $this->firstDirection);
            $this->fail('Un seul compte de direction ne doit pas pouvoir approuver.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ExpenseApprovalReadiness::UNAVAILABLE_MESSAGE,
                $exception->errors()['approval'][0] ?? null,
            );
        }

        $finance = User::factory()->active()->withRole('finance')->create();
        $this->expectException(AuthorizationException::class);
        $this->service->approve($expense, $finance);
    }

    #[Test]
    public function ac_6_refusal_requires_a_reason_and_one_refusal_is_final(): void
    {
        $expense = $this->requestedExpense();

        try {
            $this->service->refuse($expense, $this->firstDirection, '  ');
            $this->fail('Le refus sans motif doit échouer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $refused = $this->service->refuse($expense, $this->firstDirection, 'Le résultat attendu est insuffisant.');

        $this->assertSame(ExpenseState::Refusee, $refused->state);
        $this->assertDatabaseHas('expense_approvals', [
            'expense_id' => $expense->getKey(),
            'approver_id' => $this->firstDirection->getKey(),
            'decision' => ExpenseApproval::DECISION_REJECT,
            'comment' => 'Le résultat attendu est insuffisant.',
        ]);
    }

    #[Test]
    public function ac_7_every_approval_and_refusal_is_audited_with_author_and_timestamp(): void
    {
        $approvedExpense = $this->requestedExpense();
        $refusedExpense = $this->requestedExpense();

        $this->service->approve($approvedExpense, $this->firstDirection);
        $this->service->refuse($refusedExpense, $this->secondDirection, 'Dépense non justifiée.');

        foreach ([
            ['expense' => $approvedExpense, 'actor' => $this->firstDirection, 'action' => 'expense_approved', 'reason' => null],
            ['expense' => $refusedExpense, 'actor' => $this->secondDirection, 'action' => 'expense_refused', 'reason' => 'Dépense non justifiée.'],
        ] as $expected) {
            $this->assertDatabaseHas('audit_logs', [
                'auditable_type' => Expense::class,
                'auditable_id' => $expected['expense']->getKey(),
                'actor_id' => $expected['actor']->getKey(),
                'action' => $expected['action'],
                'reason' => $expected['reason'],
            ]);
            $audit = AuditLog::query()
                ->where('auditable_type', Expense::class)
                ->where('auditable_id', $expected['expense']->getKey())
                ->where('action', $expected['action'])
                ->firstOrFail();
            $this->assertNotSame('', $audit->actor_label);
            $this->assertNotNull($audit->occurred_at);
        }
    }

    #[Test]
    public function ac_8_duplicate_decisions_from_the_same_account_never_count_twice(): void
    {
        $expense = $this->requestedExpense();
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = mb_strtolower($query->sql);
        });

        $this->service->approve($expense, $this->firstDirection);

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertTrue(
                collect($queries)->contains(static fn (string $sql): bool => str_contains($sql, 'for update')),
                'La dépense doit être lue avec FOR UPDATE sur MySQL/MariaDB.',
            );
        }

        try {
            $this->service->approve($expense, $this->firstDirection);
            $this->fail('La seconde décision du même compte doit échouer après verrouillage.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('déjà', $exception->errors()['approval'][0] ?? '');
        }

        $this->assertSame(1, ExpenseApproval::query()->where('expense_id', $expense->getKey())->count());
        $this->assertSame(ExpenseState::Demandee, $expense->refresh()->state);

        $this->expectException(QueryException::class);
        ExpenseApproval::factory()->approved()->create([
            'expense_id' => $expense->getKey(),
            'approver_id' => $this->firstDirection->getKey(),
        ]);
    }

    private function requestedExpense(int $amount = 50_000, ?User $requester = null): Expense
    {
        return Expense::factory()
            ->for($requester ?? User::factory()->active()->withRole('employe'), 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create(['requested_amount' => $amount]);
    }
}
