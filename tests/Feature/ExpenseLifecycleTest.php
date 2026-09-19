<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Notifications\ExpenseRequestedNotification;
use App\Services\Finance\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ExpenseCategory $category;

    private ExpenseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withRole('employe')->create();
        $this->category = ExpenseCategory::factory()->create();
        $this->service = app(ExpenseService::class);
    }

    #[Test]
    public function can_create_expense_with_audit(): void
    {
        // AC 6: Creation produces audit entry
        Notification::fake();

        $expense = $this->service->create(
            requester: $this->user,
            category: $this->category,
            reason: 'Office supplies',
            requestedAmount: 50000,
            beneficiary: 'Supplier ABC',
            expectedResult: 'Team can work efficiently',
            projectOrContractNote: null,
            attachment: null,
            actor: $this->user,
        );

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'requester_id' => $this->user->id,
            'reason' => 'Office supplies',
            'requested_amount' => 50000,
            'beneficiary' => 'Supplier ABC',
            'state' => ExpenseState::Demandee->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Expense::class,
            'auditable_id' => $expense->id,
            'action' => 'expense_requested',
            'actor_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function can_update_pending_expense_with_audit(): void
    {
        // AC 6: Update produces audit entry
        $expense = Expense::factory()
            ->for($this->user, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create();

        $this->service->update(
            expense: $expense,
            category: null,
            reason: 'Updated office supplies',
            requestedAmount: 75000,
            beneficiary: null,
            expectedResult: null,
            projectOrContractNote: null,
            attachment: null,
            actor: $this->user,
        );

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'reason' => 'Updated office supplies',
            'requested_amount' => 75000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Expense::class,
            'auditable_id' => $expense->id,
            'action' => 'expense_updated',
            'actor_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function can_cancel_expense_with_reason_and_audit(): void
    {
        // AC 5: Cancellation requires reason, keeps record, AC 6: produces audit
        $expense = Expense::factory()
            ->for($this->user, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create();

        $this->service->cancel(
            expense: $expense,
            cancelReason: 'Budget reallocation',
            actor: $this->user,
        );

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'state' => ExpenseState::Annulee->value,
            'cancel_reason' => 'Budget reallocation',
        ]);

        // AC 5: Record is kept (not deleted)
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);

        // AC 6: Audit entry produced
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Expense::class,
            'auditable_id' => $expense->id,
            'action' => 'expense_cancelled',
            'actor_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function cancellation_reason_is_required(): void
    {
        $expense = Expense::factory()
            ->for($this->user, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create();

        try {
            $this->service->cancel(
                expense: $expense,
                cancelReason: '',
                actor: $this->user,
            );
            $this->fail("L'annulation sans motif doit échouer.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cancel_reason', $exception->errors());
        }
    }

    #[Test]
    public function cannot_update_non_pending_expense(): void
    {
        $expense = Expense::factory()
            ->for($this->user, 'requester')
            ->for($this->category, 'category')
            ->approved()
            ->create();

        $this->expectException(ValidationException::class);

        $this->service->update(
            expense: $expense,
            category: null,
            reason: 'Updated reason',
            requestedAmount: 100000,
            beneficiary: null,
            expectedResult: null,
            projectOrContractNote: null,
            attachment: null,
            actor: $this->user,
        );
    }

    #[Test]
    public function amount_stored_as_integer_xof(): void
    {
        // AC 1: Amount in XOF integer
        $expense = $this->service->create(
            requester: $this->user,
            category: $this->category,
            reason: 'Test expense',
            requestedAmount: 123456,
            beneficiary: 'Test beneficiary',
            expectedResult: 'Test result',
            projectOrContractNote: null,
            attachment: null,
            actor: $this->user,
        );

        $this->assertEquals(123456, $expense->requested_amount);
        $this->assertIsInt($expense->requested_amount);
    }

    #[Test]
    public function amount_formatted_for_display(): void
    {
        // AC 1: Amount displayed via Money::format
        $expense = Expense::factory()
            ->for($this->user, 'requester')
            ->for($this->category, 'category')
            ->requested()
            ->create(['requested_amount' => 125000]);

        $formatted = $expense->formattedAmount();

        // Should use Money::format which returns "X XXX FCFA" format
        $this->assertStringContainsString('125', $formatted);
        $this->assertStringContainsString('FCFA', $formatted);
    }

    #[Test]
    public function cannot_create_with_negative_amount(): void
    {
        try {
            $this->service->create(
                requester: $this->user,
                category: $this->category,
                reason: 'Test expense',
                requestedAmount: -1000,
                beneficiary: 'Test beneficiary',
                expectedResult: 'Test result',
                projectOrContractNote: null,
                attachment: null,
                actor: $this->user,
            );
            $this->fail('Un montant négatif doit échouer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('requested_amount', $exception->errors());
        }
    }

    #[Test]
    public function cannot_create_with_zero_amount(): void
    {
        try {
            $this->service->create(
                requester: $this->user,
                category: $this->category,
                reason: 'Test expense',
                requestedAmount: 0,
                beneficiary: 'Test beneficiary',
                expectedResult: 'Test result',
                projectOrContractNote: null,
                attachment: null,
                actor: $this->user,
            );
            $this->fail('Un montant nul doit échouer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('requested_amount', $exception->errors());
        }
    }

    #[Test]
    public function can_create_with_optional_project_note(): void
    {
        $expense = $this->service->create(
            requester: $this->user,
            category: $this->category,
            reason: 'Project expense',
            requestedAmount: 100000,
            beneficiary: 'Vendor XYZ',
            expectedResult: 'Project deliverable',
            projectOrContractNote: 'Project ALPHA - Phase 2',
            attachment: null,
            actor: $this->user,
        );

        $this->assertEquals('Project ALPHA - Phase 2', $expense->project_or_contract_note);
    }

    #[Test]
    public function notifies_approvers_on_creation(): void
    {
        // AC 4: Two approvers notified
        Notification::fake();

        $direction = User::factory()->withRole('direction')->create();
        $anotherDirection = User::factory()->withRole('direction')->create();

        $expense = $this->service->create(
            requester: $this->user,
            category: $this->category,
            reason: 'Test expense',
            requestedAmount: 50000,
            beneficiary: 'Test',
            expectedResult: 'Test result',
            projectOrContractNote: null,
            attachment: null,
            actor: $this->user,
        );

        Notification::assertSentTo(
            [$direction, $anotherDirection],
            ExpenseRequestedNotification::class,
            function ($notification, $channels) use ($expense) {
                return $notification->expense->id === $expense->id;
            }
        );
    }
}
