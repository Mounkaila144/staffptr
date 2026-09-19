<?php

namespace Tests\Feature;

use App\Enums\ExpenseState;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseApproval;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Invoice;
use App\Models\Finance\ReserveMovement;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Services\Finance\ExpenseApprovalService;
use App\Services\Finance\ExpensePaymentService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ShareEntitlementService;
use App\Services\Identity\RoleAssignmentService;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class ShareEntitlementLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(ExpenseCategorySeeder::class);
    }

    public function test_ac_43_to_46_two_half_receipts_materialize_exact_half_shares_and_no_receipt_means_zero(): void
    {
        [$actor, $client, $contract, $invoice, $account, $contributor, $executor] = $this->context();
        $this->assertDatabaseCount('share_entitlements', 0);

        $first = app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 500_000), $actor);
        $second = app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 500_000), $actor);
        $firstAmounts = $first->shareEntitlements()->orderBy('id')->pluck('share_amount')->all();
        $secondAmounts = $second->shareEntitlements()->orderBy('id')->pluck('share_amount')->all();

        $this->assertSame([50_000, 300_000, 150_000], $firstAmounts);
        $this->assertSame($firstAmounts, $secondAmounts);
        $this->assertSame(1_000_000, ShareEntitlement::query()->sum('share_amount'));
        $this->assertSame(100_000, ShareEntitlement::query()->where('beneficiary_id', $contributor->getKey())->sum('share_amount'));
        $this->assertSame(300_000, ShareEntitlement::query()->where('beneficiary_id', $executor->getKey())->sum('share_amount'));
    }

    public function test_ac_46_abandoned_half_paid_contract_has_only_half_of_all_rights(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 500_000), $actor);

        $this->assertSame(500_000, ShareEntitlement::query()->sum('share_amount'));
        $this->assertSame(500_000, $invoice->refresh()->outstandingAmount());
    }

    public function test_ac_72_73_and_95_reference_reserve_is_taken_only_from_ptr_niger_sixty_percent(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        FixedCharge::factory()->create(['monthly_amount' => 1_000_000, 'is_active' => true]);
        $payment = app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 1_000_000), $actor);
        $shares = $payment->shareEntitlements()->orderBy('id')->pluck('share_amount')->all();
        $reserve = (int) ReserveMovement::query()->where('payment_id', $payment->getKey())->value('movement_amount');

        $this->assertSame([100_000, 600_000, 300_000], $shares);
        $this->assertSame(200_000, $reserve);
        $this->assertSame(400_000, $shares[1] - $reserve);
        $this->assertSame(100_000, $shares[0]);
        $this->assertSame(300_000, $shares[2]);
    }

    public function test_ac_48_and_95_origin_method_period_and_unique_source_are_persisted(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $payment = app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 100_000), $actor);
        $share = $payment->shareEntitlements()->firstOrFail();

        $this->assertStringContainsString('10 % apporteur', $share->calculation_method);
        $this->assertSame($payment->received_on->toDateString(), $share->source_received_on->toDateString());
        $this->assertSame($payment->getKey(), $share->payment_id);

        $this->expectException(QueryException::class);
        ShareEntitlement::query()->create($share->only([
            'contract_id', 'payment_id', 'beneficiary_id', 'beneficiary_key', 'share_type', 'base_amount', 'rate_basis_points',
            'rate_divisor', 'share_amount', 'paid_amount', 'calculation_method', 'period_start', 'period_end', 'source_received_on',
        ]));
    }

    public function test_contra_05_non_associate_sees_only_own_line_while_financial_roles_see_all(): void
    {
        [$actor, $client, $contract, $invoice, $account, $contributor, $executor] = $this->context();
        app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 100_000), $actor);
        $employeeRows = app(ShareEntitlementService::class)->forViewer($contributor);
        $finance = $this->roleUser('finance');
        $financeRows = app(ShareEntitlementService::class)->forViewer($finance);

        $this->assertCount(1, $employeeRows);
        $this->assertSame($contributor->getKey(), $employeeRows[0]['beneficiary_id']);
        $this->assertCount(3, $financeRows);
    }

    public function test_ac_53_to_58_own_share_becomes_ordinary_expense_with_base_rate_contract_and_audit(): void
    {
        [$actor, $client, $contract, $invoice, $account, $contributor] = $this->context();
        app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 100_000), $actor);
        $share = ShareEntitlement::query()->where('beneficiary_id', $contributor->getKey())->sole();
        $expense = app(ShareEntitlementService::class)->requestPayment($share, $contributor);

        $this->assertSame(ExpenseState::Demandee, $expense->state);
        $this->assertSame($share->getKey(), $expense->share_entitlement_id);
        $this->assertSame($contract->getKey(), $expense->contract_id);
        $this->assertSame($contributor->getKey(), $expense->beneficiary_user_id);
        $this->assertStringContainsString('taux de 10 %', $expense->expected_result);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Expense::class, 'auditable_id' => $expense->getKey(), 'action' => 'share_payment_requested']);
    }

    public function test_ac_55_associate_beneficiary_cannot_approve_own_share(): void
    {
        $beneficiary = $this->roleUser('direction');
        $expense = Expense::factory()->create([
            'requester_id' => User::factory()->active(), 'state' => ExpenseState::Demandee->value,
            'beneficiary_user_id' => $beneficiary->getKey(),
        ]);

        try {
            app(ExpenseApprovalService::class)->approve($expense, $beneficiary);
            $this->fail('Le bénéficiaire ne doit pas approuver sa propre part.');
        } catch (ValidationException $exception) {
            $this->assertSame('Un bénéficiaire ne peut pas approuver sa propre part.', $exception->errors()['approval'][0]);
        }
    }

    public function test_ac_47_53_and_56_paid_and_remaining_follow_the_ordinary_expense_payment(): void
    {
        [$actor, $client, $contract, $invoice, $account, $contributor, $executor] = $this->context();
        app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, 100_000), $actor);
        $share = ShareEntitlement::query()->where('beneficiary_id', $contributor->getKey())->sole();
        $expense = app(ShareEntitlementService::class)->requestPayment($share, $contributor);
        $expense->forceFill(['state' => ExpenseState::Approuvee->value])->saveQuietly();
        foreach ([$executor, $this->roleUser('direction')] as $approver) {
            ExpenseApproval::query()->create([
                'expense_id' => $expense->getKey(), 'approver_id' => $approver->getKey(),
                'decision' => ExpenseApproval::DECISION_APPROVE, 'decided_at' => now('UTC'),
            ]);
        }
        $finance = $this->roleUser('finance');
        app(ExpensePaymentService::class)->pay($expense, [
            'account_id' => $account->getKey(), 'paid_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'),
            'payment_mode' => 'especes', 'payment_reference' => 'PART-TEST', 'project_id' => null,
            'contract_id' => $contract->getKey(), 'attachment_ulid' => null, 'is_advance_reimbursement' => false,
            'payment_idempotency_key' => (string) Str::ulid(),
        ], $finance);

        $this->assertSame($share->share_amount, $share->refresh()->paid_amount);
        $this->assertSame(0, $share->remainingAmount());
        $this->assertDatabaseHas('account_movements', ['source_type' => Expense::class, 'source_id' => $expense->getKey(), 'direction' => 'debit']);
    }

    /** @return array{User, Client, Contract, Invoice, Account, User, User} */
    private function context(): array
    {
        $actor = User::factory()->active()->create();
        $contributor = $this->roleUser('employe');
        $executor = $this->roleUser('direction');
        $client = Client::factory()->create();
        $contract = Contract::factory()->create([
            'client_id' => $client->getKey(), 'expected_total_amount' => 1_000_000, 'forecast_profit_amount' => 1_000_000,
            'contributor_id' => $contributor->getKey(), 'has_execution' => true,
        ]);
        $contract->executorHistory()->create(['user_id' => $executor->getKey(), 'position' => 1, 'is_active' => true]);
        $invoice = Invoice::factory()->create(['client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'total_amount' => 1_000_000]);
        $account = Account::factory()->create();

        return [$actor, $client, $contract, $invoice, $account, $contributor, $executor];
    }

    /** @return array<string, mixed> */
    private function payload(Client $client, Contract $contract, Invoice $invoice, Account $account, int $amount): array
    {
        return [
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'project_id' => null,
            'invoice_id' => $invoice->getKey(), 'account_id' => $account->getKey(), 'received_amount' => $amount,
            'received_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'), 'payment_mode' => 'especes',
            'reference' => null, 'attachment_ulid' => null, 'idempotency_key' => (string) Str::ulid(),
        ];
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test parts story 8.1');

        return $user;
    }
}
