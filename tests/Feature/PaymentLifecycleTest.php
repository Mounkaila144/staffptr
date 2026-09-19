<?php

namespace Tests\Feature;

use App\Enums\InvoiceState;
use App\Enums\PaymentState;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Invoice;
use App\Models\Finance\MonthClosure;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Finance\PaymentService;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\IdentityTestCase;

class PaymentLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_28_31_34_51_payment_is_atomic_and_updates_account_invoice_parts_attachment_and_audit(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $attachment = Attachment::factory()->create([
            'attachable_type' => $actor->person->getMorphClass(), 'attachable_id' => $actor->person_id, 'uploaded_by' => $actor->getKey(),
        ]);
        $payment = app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, [
            'received_amount' => 250_000, 'attachment_ulid' => $attachment->ulid,
        ]), $actor);

        $this->assertMatchesRegularExpression('/^REC-[0-9]{10}$/', $payment->receipt_number);
        $this->assertSame(251_000, $account->currentBalance());
        $this->assertSame(InvoiceState::PartiellementPayee, $invoice->refresh()->state);
        $this->assertDatabaseHas('account_movements', ['source_id' => $payment->getKey(), 'direction' => 'credit', 'movement_amount' => 250_000]);
        $this->assertDatabaseHas('share_entitlements', ['payment_id' => $payment->getKey(), 'base_amount' => 125_000, 'share_amount' => 125_000]);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->getKey(), 'attachable_type' => Payment::class, 'attachable_id' => $payment->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Payment::class, 'auditable_id' => $payment->getKey(), 'action' => 'payment_recorded']);
    }

    public function test_ac_29_idempotent_retry_returns_same_receipt_and_never_consumes_a_second_number(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $payload = $this->payload($client, $contract, $invoice, $account);
        $first = app(PaymentService::class)->record($payload, $actor);
        $second = app(PaymentService::class)->record($payload, $actor);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('receipt_sequences', 1);
        $this->assertDatabaseCount('account_movements', 1);
    }

    public function test_ac_32_late_recording_uses_utc_timestamps_and_flags_more_than_twenty_four_hours(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 8, 17)->setTime(12, 0));
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $payment = app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, [
            'received_at' => '2026-08-15T10:00',
        ]), $actor);

        $this->assertTrue($payment->late_recording);
        $this->assertSame('2026-08-15 09:00:00', $payment->received_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_ac_34_interruption_rolls_back_receipt_movement_parts_reserve_and_audit(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $contract->forceFill(['expected_total_amount' => 0])->saveQuietly();

        try {
            app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account), $actor);
            $this->fail('Le calcul invalide devait interrompre la transaction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('montant total attendu', $exception->getMessage());
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('account_movements', 0);
        $this->assertDatabaseCount('receipt_sequences', 0);
        $this->assertDatabaseMissing('audit_logs', ['auditable_type' => Payment::class]);
    }

    public function test_ac_29_30_correction_and_cancellation_keep_versions_use_new_receipts_and_balance_exactly(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $service = app(PaymentService::class);
        $original = $service->record($this->payload($client, $contract, $invoice, $account, ['received_amount' => 50_000]), $actor);
        $corrected = $service->correct($original, $this->payload($client, $contract, $invoice, $account, [
            'received_amount' => 70_000, 'correction_reason' => 'Montant réel confirmé par le relevé.',
        ]), $actor);

        $this->assertSame(PaymentState::Corrected, $original->refresh()->state);
        $this->assertSame($original->getKey(), $corrected->correction_of_id);
        $this->assertSame(71_000, $account->currentBalance());
        $this->assertDatabaseHas('share_entitlements', ['payment_id' => $original->getKey(), 'reversal_payment_id' => $corrected->getKey()]);

        $reversal = $service->cancel($corrected, 'Annulation confirmée par la direction.', (string) Str::ulid(), $actor);
        $this->assertSame(PaymentState::Cancelled, $corrected->refresh()->state);
        $this->assertSame($corrected->getKey(), $reversal->reversal_of_id);
        $this->assertSame(1_000, $account->currentBalance());
        $this->assertCount(3, array_unique(Payment::query()->pluck('receipt_number')->all()));
        $this->assertSame(InvoiceState::Impayee, $invoice->refresh()->state);
    }

    public function test_ac_33_closed_month_refuses_payment_before_any_sequence_is_consumed(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        MonthClosure::factory()->create(['month' => '2026-06-01', 'reopened_at' => null]);

        $this->expectException(ValidationException::class);
        try {
            app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, ['received_at' => '2026-06-15T12:00']), $actor);
        } finally {
            $this->assertDatabaseCount('receipt_sequences', 0);
        }
    }

    public function test_ac_51_and_71_reserve_is_materialized_in_the_same_payment_transaction(): void
    {
        $associate = User::factory()->active()->leader()->create();
        app(RoleAssignmentService::class)->assignRole($associate, 'direction', null, 'Associé test');
        [$actor, $client, $contract, $invoice, $account] = $this->context([
            'contributor_id' => User::factory()->active()->employee(), 'has_execution' => true,
        ]);
        $contract->executorHistory()->create(['user_id' => $associate->getKey(), 'position' => 1, 'is_active' => true]);
        FixedCharge::factory()->create(['monthly_amount' => 1_000_000, 'is_active' => true]);

        $payment = app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, ['received_amount' => 500_000]), $actor);

        $this->assertDatabaseHas('reserve_movements', ['payment_id' => $payment->getKey(), 'type' => 'allocation', 'movement_amount' => 50_000]);
    }

    /** @param array<string, mixed> $contractOverrides
     * @return array{User, Client, Contract, Invoice, Account}
     */
    private function context(array $contractOverrides = []): array
    {
        $actor = User::factory()->active()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->create(array_replace([
            'client_id' => $client->getKey(), 'expected_total_amount' => 1_000_000, 'forecast_profit_amount' => 500_000,
        ], $contractOverrides));
        $invoice = Invoice::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'total_amount' => 1_000_000,
        ]);
        $account = Account::factory()->create(['opening_balance_amount' => 1_000]);

        return [$actor, $client, $contract, $invoice, $account];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Client $client, Contract $contract, Invoice $invoice, Account $account, array $overrides = []): array
    {
        return array_replace([
            'client_id' => (int) $client->getKey(), 'contract_id' => (int) $contract->getKey(), 'project_id' => null,
            'invoice_id' => (int) $invoice->getKey(), 'account_id' => (int) $account->getKey(), 'received_amount' => 100_000,
            'received_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'), 'payment_mode' => 'especes', 'reference' => 'REF-TEST',
            'attachment_ulid' => null, 'idempotency_key' => (string) Str::ulid(),
        ], $overrides);
    }
}
