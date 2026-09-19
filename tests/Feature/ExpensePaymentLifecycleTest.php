<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\Invoice;
use App\Models\Finance\MonthClosure;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Finance\ExpensePaymentService;
use App\Services\Finance\PaymentService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class ExpensePaymentLifecycleTest extends IdentityTestCase
{
    public function test_ac_35_36_only_approved_expense_with_two_approvals_is_payable(): void
    {
        $actor = User::factory()->active()->create();
        $account = Account::factory()->create();
        foreach ([ExpenseState::Demandee, ExpenseState::Refusee] as $state) {
            $expense = Expense::factory()->create(['state' => $state->value]);
            try {
                app(ExpensePaymentService::class)->pay($expense, $this->payload($account), $actor);
                $this->fail('Une dépense non approuvée ne doit pas être payable.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('state', $exception->errors());
            }
        }
        $this->assertDatabaseCount('account_movements', 0);
    }

    public function test_ac_36_38_39_payment_debits_account_records_imputation_and_lists_missing_attachment(): void
    {
        $actor = User::factory()->active()->create();
        $account = Account::factory()->create(['opening_balance_amount' => 100_000]);
        $contract = Contract::factory()->create();
        $expense = $this->approvedExpense(['requested_amount' => 20_000]);
        $paid = app(ExpensePaymentService::class)->pay($expense, $this->payload($account, ['contract_id' => $contract->getKey()]), $actor);
        $management = app(ExpensePaymentService::class)->forManagement();

        $this->assertSame(ExpenseState::Payee, $paid->state);
        $this->assertSame(80_000, $account->currentBalance());
        $this->assertSame($contract->getKey(), $paid->contract_id);
        $this->assertCount(1, $management['missing_attachments']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Expense::class, 'auditable_id' => $expense->getKey(), 'action' => 'expense_paid']);
    }

    public function test_ac_37_receipt_fifty_thousand_then_expense_twenty_thousand_moves_balance_exactly_thirty_thousand(): void
    {
        $actor = User::factory()->active()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->create(['client_id' => $client->getKey(), 'expected_total_amount' => 100_000, 'forecast_profit_amount' => 50_000]);
        $invoice = Invoice::factory()->create(['client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'total_amount' => 100_000]);
        $account = Account::factory()->create(['opening_balance_amount' => 0]);
        app(PaymentService::class)->record([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'project_id' => null, 'invoice_id' => $invoice->getKey(),
            'account_id' => $account->getKey(), 'received_amount' => 50_000, 'received_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'),
            'payment_mode' => 'especes', 'reference' => null, 'attachment_ulid' => null, 'idempotency_key' => (string) Str::ulid(),
        ], $actor);
        app(ExpensePaymentService::class)->pay($this->approvedExpense(['requested_amount' => 20_000]), $this->payload($account), $actor);

        $this->assertSame(30_000, $account->currentBalance());
    }

    public function test_ac_38_payment_attachment_leaves_missing_list_after_regularization(): void
    {
        $actor = User::factory()->active()->create();
        $attachment = Attachment::factory()->create([
            'attachable_type' => $actor->person->getMorphClass(), 'attachable_id' => $actor->person_id, 'uploaded_by' => $actor->getKey(),
        ]);
        $expense = $this->approvedExpense();
        app(ExpensePaymentService::class)->pay($expense, $this->payload(Account::factory()->create(), ['attachment_ulid' => $attachment->ulid]), $actor);

        $this->assertSame([], app(ExpensePaymentService::class)->forManagement()['missing_attachments']);
        $this->assertDatabaseHas('expenses', ['id' => $expense->getKey(), 'payment_attachment_id' => $attachment->getKey()]);
    }

    public function test_ac_40_advance_reimbursement_requires_original_receipt(): void
    {
        $expense = $this->approvedExpense();

        $this->expectException(ValidationException::class);
        app(ExpensePaymentService::class)->pay($expense, $this->payload(Account::factory()->create(), ['is_advance_reimbursement' => true]), User::factory()->active()->create());
    }

    public function test_ac_41_paid_expense_cancellation_creates_counter_entry_and_restores_balance(): void
    {
        $actor = User::factory()->active()->create();
        $account = Account::factory()->create(['opening_balance_amount' => 100_000]);
        $expense = app(ExpensePaymentService::class)->pay($this->approvedExpense(['requested_amount' => 20_000]), $this->payload($account), $actor);
        $counter = app(ExpensePaymentService::class)->cancel($expense, 'Paiement annulé sur confirmation écrite.', (string) Str::ulid(), $actor);

        $this->assertSame(100_000, $account->currentBalance());
        $this->assertSame(ExpenseState::Annulee, $expense->refresh()->state);
        $this->assertSame($expense->getKey(), $counter->counter_entry_of_id);
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_ac_42_closed_month_blocks_expense_payment(): void
    {
        MonthClosure::factory()->create(['month' => '2026-06-01', 'reopened_at' => null]);

        $this->expectException(ValidationException::class);
        app(ExpensePaymentService::class)->pay(
            $this->approvedExpense(),
            $this->payload(Account::factory()->create(), ['paid_at' => '2026-06-15T12:00']),
            User::factory()->active()->create(),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function approvedExpense(array $overrides = []): Expense
    {
        $expense = Expense::factory()->create(array_replace(['state' => ExpenseState::Approuvee->value], $overrides));
        foreach ([User::factory()->active()->create(), User::factory()->active()->create()] as $approver) {
            ExpenseApproval::query()->create([
                'expense_id' => $expense->getKey(), 'approver_id' => $approver->getKey(),
                'decision' => ExpenseApproval::DECISION_APPROVE, 'decided_at' => now('UTC'),
            ]);
        }

        return $expense;
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Account $account, array $overrides = []): array
    {
        return array_replace([
            'account_id' => (int) $account->getKey(), 'paid_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'),
            'payment_mode' => 'especes', 'payment_reference' => null, 'project_id' => null, 'contract_id' => null,
            'attachment_ulid' => null, 'is_advance_reimbursement' => false, 'payment_idempotency_key' => (string) Str::ulid(),
        ], $overrides);
    }
}
