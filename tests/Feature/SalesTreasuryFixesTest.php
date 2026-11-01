<?php

namespace Tests\Feature;

use App\Enums\PaymentState;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Finance\AlertLevelService;
use App\Services\Finance\FinancialDashboardService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\ReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

/**
 * Corrections issues du test de bout en bout « ventes et trésorerie » (facturation, encaissements,
 * tableau de bord financier).
 */
class SalesTreasuryFixesTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    /**
     * Le dernier jour du mois compte, et une contre-écriture (reçu « validé » qui annule un
     * encaissement) n'est pas une rentrée d'argent.
     */
    public function test_month_collections_include_last_day_and_exclude_counter_entries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 15:00', 'Africa/Niamey'));
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $service = app(PaymentService::class);
        $kept = $service->record($this->payload($client, $contract, $invoice, $account, ['received_amount' => 600_000]), $actor);
        $cancelled = $service->record($this->payload($client, $contract, $invoice, $account, ['received_amount' => 50_000]), $actor);
        $service->cancel($cancelled, 'Billets refusés par la banque.', (string) Str::ulid(), $actor);

        $block = app(FinancialDashboardService::class)->monthCollections();

        $this->assertSame(1, $block['count']);
        $this->assertSame(600_000, $block['amount']);
        $this->assertSame(600_000, app(AlertLevelService::class)->collectionsFor(CarbonImmutable::parse('2026-10-01', 'Africa/Niamey')));
        $this->assertTrue($kept->refresh()->state === PaymentState::Validated);
    }

    /** Une clé d'annulation déjà consommée ne doit pas « annuler » silencieusement un autre reçu. */
    public function test_reused_cancellation_key_on_another_receipt_is_refused(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $service = app(PaymentService::class);
        $first = $service->record($this->payload($client, $contract, $invoice, $account, ['received_amount' => 50_000]), $actor);
        $second = $service->record($this->payload($client, $contract, $invoice, $account, ['received_amount' => 40_000]), $actor);
        $key = (string) Str::ulid();
        $service->cancel($first, 'Billets refusés par la banque.', $key, $actor);

        try {
            $service->cancel($second, 'Deuxième annulation avec la même clé.', $key, $actor);
            $this->fail('La clé déjà consommée devait être refusée pour un autre reçu.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $this->assertSame(PaymentState::Validated, $second->refresh()->state);
        // Le même envoi rejoué pour le même reçu reste idempotent.
        $this->assertSame(
            (int) $service->cancel($first, 'Billets refusés par la banque.', $key, $actor)->reversal_of_id,
            (int) $first->getKey(),
        );
    }

    public function test_client_and_contract_validation_messages_are_explicit(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $client = Client::factory()->create();

        $this->actingAs($finance)->post('/finances/clients', ['name' => 'Client', 'phone' => '123', 'is_active' => true])
            ->assertSessionHasErrors(['phone' => 'Saisissez les 8 chiffres du numéro après +227, par exemple 96 11 22 33.']);

        $this->actingAs($finance)->post('/finances/contrats', [
            'client_id' => $client->getKey(), 'reference' => 'TV-1', 'title' => 'Test', 'expected_total_amount' => 1_000,
            'forecast_profit_amount' => 2_000, 'has_execution' => false, 'executor_ids' => [],
        ])->assertSessionHasErrors(['forecast_profit_amount' => 'Le bénéfice prévisionnel ne peut pas dépasser le montant total prévu du contrat.']);

        $this->actingAs($finance)->post('/finances/encaissements', [
            'client_id' => $client->getKey(), 'account_id' => Account::factory()->create()->getKey(), 'received_amount' => 1_000,
            'received_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'), 'payment_mode' => 'especes', 'idempotency_key' => (string) Str::ulid(),
        ])->assertSessionHasErrors(['contract_id' => 'Choisissez le contrat concerné ou, à défaut, un projet.']);
    }

    public function test_payment_listing_links_to_its_private_attachment(): void
    {
        [$actor, $client, $contract, $invoice, $account] = $this->context();
        $attachment = Attachment::factory()->create([
            'attachable_type' => $actor->person->getMorphClass(), 'attachable_id' => $actor->person_id, 'uploaded_by' => $actor->getKey(),
        ]);
        app(PaymentService::class)->record($this->payload($client, $contract, $invoice, $account, ['attachment_ulid' => $attachment->ulid]), $actor);

        $row = app(PaymentService::class)->forManagement()[0];

        $this->assertSame(route('attachments.show', $attachment->refresh(), absolute: false), $row['attachment_url']);
    }

    /** La fiche de rapprochement porte de quoi préremplir le formulaire de correction. */
    public function test_reconciliation_listing_exposes_fields_needed_to_prefill_a_correction(): void
    {
        $finance = User::factory()->active()->withRole('finance')->create();
        $responsible = User::factory()->active()->create();
        $account = Account::factory()->create(['opening_balance_amount' => 10_000, 'opening_balance_date' => '2026-10-01']);
        app(ReconciliationService::class)->prepare([
            'account_id' => $account->getKey(), 'period_start' => '2026-10-01', 'period_end' => '2026-10-07',
            'physical_balance_amount' => 9_000, 'difference_explanation' => 'Pièce manquante', 'responsible_id' => $responsible->getKey(),
            'corrective_action' => 'Recompter',
        ], $finance);

        $row = app(ReconciliationService::class)->listing($finance)[0];

        $this->assertSame((int) $account->getKey(), $row['account_id']);
        $this->assertSame(9_000, $row['physical_balance_amount']);
        $this->assertSame((int) $responsible->getKey(), $row['responsible_id']);
    }

    /** @return array{User, Client, Contract, Invoice, Account} */
    private function context(): array
    {
        $actor = User::factory()->active()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->create([
            'client_id' => $client->getKey(), 'expected_total_amount' => 1_000_000, 'forecast_profit_amount' => 500_000,
        ]);
        $invoice = Invoice::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'total_amount' => 1_000_000,
        ]);

        return [$actor, $client, $contract, $invoice, Account::factory()->create(['opening_balance_amount' => 1_000])];
    }

    /**
     * @param  array<string, mixed>  $overrides
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
