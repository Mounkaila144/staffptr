<?php

namespace Tests\Feature;

use App\Enums\FinancialAccountState;
use App\Enums\FinancialAccountType;
use App\Enums\InvoiceState;
use App\Enums\PaymentState;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class FinanceCoreModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_1_factories_create_the_financial_dependency_chain(): void
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->for($client)->create();
        $invoice = Invoice::factory()->for($client)->for($contract)->create();
        $account = Account::factory()->create();
        $payment = Payment::factory()
            ->for($client)
            ->for($contract)
            ->for($invoice)
            ->for($account)
            ->create();

        $this->assertTrue($contract->client->is($client));
        $this->assertTrue($invoice->contract->is($contract));
        $this->assertTrue($payment->invoice->is($invoice));
        $this->assertSame(InvoiceState::Impayee, $invoice->state);
        $this->assertSame(PaymentState::Validated, $payment->state);
        $this->assertIsInt($payment->received_amount);
    }

    public function test_ac_1_account_uses_typed_state_type_and_integer_xof(): void
    {
        $account = Account::factory()->create([
            'type' => FinancialAccountType::MobileMoney->value,
            'state' => FinancialAccountState::Active->value,
            'opening_balance_amount' => 125_000,
        ]);

        $this->assertSame(FinancialAccountType::MobileMoney, $account->type);
        $this->assertSame(FinancialAccountState::Active, $account->state);
        $this->assertSame(125_000, $account->opening_balance_amount);
    }

    public function test_ac_6_financial_models_refuse_physical_deletion(): void
    {
        $account = Account::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('suppression physique');

        $account->delete();
    }
}
