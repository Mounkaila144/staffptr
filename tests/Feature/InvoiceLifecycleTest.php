<?php

namespace Tests\Feature;

use App\Enums\InvoiceState;
use App\Enums\PaymentState;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Finance\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class InvoiceLifecycleTest extends IdentityTestCase
{
    public function test_ac_21_and_22_number_is_system_generated_unique_and_initial_state_is_unpaid(): void
    {
        $actor = User::factory()->active()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->create(['client_id' => $client->getKey()]);
        $service = app(InvoiceService::class);
        $first = $service->create($this->payload($client, $contract), $actor);
        $second = $service->create($this->payload($client, $contract), $actor);

        $this->assertMatchesRegularExpression('/^FAC-[0-9]{6}-[0-9A-Z]{10}$/', $first->number);
        $this->assertNotSame($first->number, $second->number);
        $this->assertSame(InvoiceState::Impayee, $first->state);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Invoice::class, 'auditable_id' => $first->getKey(), 'action' => 'invoice_created']);
    }

    public function test_ac_22_and_31_state_is_derived_from_validated_receipts(): void
    {
        [$invoice, $client, $contract] = $this->invoice(100_000);

        Payment::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'invoice_id' => $invoice->getKey(),
            'received_amount' => 40_000, 'state' => PaymentState::Validated->value,
        ]);
        DB::transaction(fn (): Invoice => app(InvoiceService::class)->refreshDerivedState($invoice));
        $this->assertSame(InvoiceState::PartiellementPayee, $invoice->refresh()->state);
        $this->assertSame(60_000, $invoice->outstandingAmount());

        Payment::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'invoice_id' => $invoice->getKey(),
            'received_amount' => 60_000, 'state' => PaymentState::Validated->value,
        ]);
        DB::transaction(fn (): Invoice => app(InvoiceService::class)->refreshDerivedState($invoice));
        $this->assertSame(InvoiceState::Payee, $invoice->refresh()->state);
        $this->assertSame(0, $invoice->outstandingAmount());
    }

    public function test_ac_23_24_and_27_receivables_are_automatic_with_remaining_amount_and_age_sort(): void
    {
        CarbonImmutable::setTestNow('2026-08-17 10:00:00');
        [$old] = $this->invoice(100_000, ['due_on' => '2026-07-01']);
        [$recent] = $this->invoice(80_000, ['due_on' => '2026-08-10']);
        [$future] = $this->invoice(70_000, ['due_on' => '2026-09-01']);

        $result = app(InvoiceService::class)->forManagement(['sort' => 'age_desc']);

        $this->assertSame([$old->getKey(), $recent->getKey()], array_column($result['receivables'], 'id'));
        $this->assertSame(47, $result['receivables'][0]['age_days']);
        $this->assertSame(100_000, $result['receivables'][0]['outstanding_amount']);
        $this->assertNotContains($future->getKey(), array_column($result['receivables'], 'id'));
    }

    public function test_ac_25_cancellation_requires_reason_is_audited_and_never_deletes(): void
    {
        [$invoice] = $this->invoice(100_000);
        $actor = User::factory()->active()->create();
        app(InvoiceService::class)->cancel($invoice, 'Erreur de facturation confirmée.', $actor);

        $this->assertSame(InvoiceState::Annulee, $invoice->refresh()->state);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->getKey(), 'cancellation_reason' => 'Erreur de facturation confirmée.']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Invoice::class, 'auditable_id' => $invoice->getKey(), 'action' => 'invoice_cancelled']);

        $this->expectException(\LogicException::class);
        $invoice->delete();
    }

    public function test_paid_invoice_cannot_be_cancelled_without_reversing_its_receipt(): void
    {
        [$invoice, $client, $contract] = $this->invoice(100_000);
        Payment::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'invoice_id' => $invoice->getKey(), 'received_amount' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->cancel($invoice, 'Tentative après encaissement.', User::factory()->active()->create());
    }

    /** @param array<string, mixed> $overrides
     * @return array{Invoice, Client, Contract}
     */
    private function invoice(int $amount, array $overrides = []): array
    {
        $client = Client::factory()->create();
        $contract = Contract::factory()->create(['client_id' => $client->getKey()]);
        $invoice = app(InvoiceService::class)->create(array_replace($this->payload($client, $contract), [
            'total_amount' => $amount,
        ], $overrides), User::factory()->active()->create());

        return [$invoice, $client, $contract];
    }

    /** @return array{client_id: int, contract_id: int, total_amount: int, issued_on: string, due_on: string} */
    private function payload(Client $client, Contract $contract): array
    {
        return [
            'client_id' => (int) $client->getKey(), 'contract_id' => (int) $contract->getKey(),
            'total_amount' => 100_000, 'issued_on' => '2026-08-01', 'due_on' => '2026-08-31',
        ];
    }
}
