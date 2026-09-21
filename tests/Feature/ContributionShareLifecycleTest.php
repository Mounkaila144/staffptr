<?php

namespace Tests\Feature;

use App\Enums\CapitalContributionState;
use App\Enums\ContributionEntryType;
use App\Enums\ContributionOrigin;
use App\Enums\ShareType;
use App\Models\Finance\Account;
use App\Models\Finance\CapitalContribution;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\ContributionShare;
use App\Models\Finance\Payment;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Services\Finance\CapitalContributionService;
use App\Services\Finance\ContributionShareService;
use App\Services\Finance\PaymentService;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Platform\Invariants\ContributionShareIntegrityInvariant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;
use Throwable;

/**
 * Registre des parts de contribution des directeurs — story 12.1.
 *
 * Toutes les preuves se placent en **année 1** d'activité (× 2) sauf mention contraire, pour que
 * le coefficient attendu soit lisible dans l'assertion plutôt que déduit de la date du jour.
 */
class ContributionShareLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->travelTo(now('UTC')->setDate(2026, 10, 15)->setTime(9, 0));
    }

    public function test_ac_1_and_13_a_payment_with_a_director_contributor_issues_shares_on_the_company_part(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);

        $payment = $this->record($actor, $client, $contract, $account, 200_000);

        // Base 100 000 → 10 % apporteur (10 000) et 90 % entreprise (90 000). Seuls ces 90 000
        // deviennent des parts, revalorisés × 2 par le millésime de l'année 1.
        $this->assertDatabaseHas('share_entitlements', [
            'payment_id' => $payment->getKey(), 'share_type' => ShareType::PtrNiger->value, 'share_amount' => 90_000,
        ]);
        $this->assertDatabaseHas('contribution_shares', [
            'holder_id' => $director->getKey(),
            'origin' => ContributionOrigin::Encaissement->value,
            'entry_type' => ContributionEntryType::Emission->value,
            'base_amount' => 90_000,
            'vintage_year' => 1,
            'coefficient_basis_points' => 20_000,
            'issued_shares' => 180_000,
        ]);
        $this->assertSame(
            '2026-10-15',
            ContributionShare::query()->where('payment_id', $payment->getKey())->firstOrFail()->occurred_on->toDateString(),
        );
        $this->assertSame(180_000, app(ContributionShareService::class)->totalFor($director));
    }

    /** AC 2 — les 10 % d'apporteur et les 30 % d'exécutant ont déjà rémunéré : ils n'émettent rien. */
    public function test_ac_2_contributor_and_executor_entitlements_never_issue_contribution_shares(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: true);
        $contract->executors()->create(['user_id' => $this->employee()->getKey(), 'position' => 1, 'is_active' => true]);

        $payment = $this->record($actor, $client, $contract, $account, 200_000);
        $entries = ContributionShare::query()->where('payment_id', $payment->getKey())->get();

        $this->assertCount(1, $entries, 'Un encaissement n’émet qu’une ligne de parts, celle de la part entreprise.');
        $this->assertSame(60_000, $entries->first()?->base_amount);
        $this->assertSame(120_000, $entries->first()?->issued_shares);
        $companyOnly = ShareEntitlement::query()
            ->whereIn('id', ContributionShare::query()->pluck('share_entitlement_id')->all())
            ->pluck('share_type')
            ->all();
        $this->assertSame([ShareType::PtrNiger->value], array_map(
            static fn (mixed $type): string => $type instanceof ShareType ? $type->value : (string) $type,
            $companyOnly,
        ));
    }

    public function test_ac_3_a_payment_without_a_contributor_issues_no_shares(): void
    {
        [$actor, $client, $contract, $account] = $this->context(contributor: null, hasExecution: false);

        $payment = $this->record($actor, $client, $contract, $account, 200_000);

        $this->assertDatabaseHas('share_entitlements', [
            'payment_id' => $payment->getKey(), 'share_type' => ShareType::PtrNiger->value, 'share_amount' => 100_000,
        ]);
        $this->assertDatabaseCount('contribution_shares', 0);
    }

    public function test_ac_4_a_contributor_who_is_not_a_director_issues_nothing_and_keeps_the_ten_percent(): void
    {
        $employee = $this->employee();
        [$actor, $client, $contract, $account] = $this->context($employee, hasExecution: false);

        $payment = $this->record($actor, $client, $contract, $account, 200_000);

        $this->assertDatabaseHas('share_entitlements', [
            'payment_id' => $payment->getKey(),
            'beneficiary_id' => $employee->getKey(),
            'share_type' => ShareType::Contributor->value,
            'share_amount' => 10_000,
        ]);
        $this->assertDatabaseCount('contribution_shares', 0);
    }

    public function test_ac_5_replaying_the_issuance_of_a_payment_does_not_duplicate_the_shares(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $payment = $this->record($actor, $client, $contract, $account, 200_000);

        $replayed = DB::transaction(fn (): ?ContributionShare => app(ContributionShareService::class)
            ->issueForPayment($payment->refresh(), $contract->refresh(), $actor));

        $this->assertDatabaseCount('contribution_shares', 1);
        $this->assertSame(180_000, app(ContributionShareService::class)->totalFor($director));
        $this->assertNotNull($replayed);
    }

    public function test_ac_6_cancelling_a_payment_writes_a_dated_counter_entry_and_deletes_nothing(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $payment = $this->record($actor, $client, $contract, $account, 200_000);

        $this->travelTo(now('UTC')->setDate(2026, 11, 3)->setTime(9, 0));
        app(PaymentService::class)->cancel($payment, 'Encaissement saisi en double.', (string) Str::ulid(), $actor);

        $this->assertDatabaseCount('contribution_shares', 2);
        $reversal = ContributionShare::query()->where('entry_type', ContributionEntryType::Annulation->value)->firstOrFail();
        $this->assertSame(180_000, $reversal->issued_shares);
        $this->assertSame('2026-11-03', $reversal->occurred_on->toDateString());
        $this->assertSame((int) $payment->getKey(), (int) ContributionShare::query()
            ->whereKey($reversal->reversal_of_id)->firstOrFail()->payment_id);
        $this->assertDatabaseHas('contribution_shares', [
            'entry_type' => ContributionEntryType::Emission->value,
            'issued_shares' => 180_000,
        ]);
        $this->assertSame(0, app(ContributionShareService::class)->totalFor($director));
    }

    /** AC 6 — une correction extourne les parts de l'encaissement corrigé et en émet de nouvelles. */
    public function test_ac_6_correcting_a_payment_reverses_the_old_shares_and_issues_the_corrected_ones(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $payment = $this->record($actor, $client, $contract, $account, 200_000);

        app(PaymentService::class)->correct($payment, [
            'client_id' => (int) $client->getKey(),
            'contract_id' => (int) $contract->getKey(),
            'project_id' => null,
            'account_id' => (int) $account->getKey(),
            'received_amount' => 400_000,
            'received_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'),
            'payment_mode' => 'especes',
            'reference' => 'REF-12-1-CORR',
            'attachment_ulid' => null,
            'idempotency_key' => (string) Str::ulid(),
            'correction_reason' => 'Montant saisi à moitié.',
        ], $actor);

        $this->assertDatabaseCount('contribution_shares', 3);
        // 180 000 émis puis annulés, puis 360 000 émis sur la base corrigée de 180 000.
        $this->assertSame(360_000, app(ContributionShareService::class)->totalFor($director));
        $this->assertTrue(app(ContributionShareIntegrityInvariant::class)->check()->passed);
    }

    public function test_ac_7_8_and_9_a_capital_contribution_waits_for_the_other_director(): void
    {
        $first = $this->director();
        $second = $this->director();
        $service = app(CapitalContributionService::class);

        $contribution = $service->record(2_000_000, 'Renforcement de la trésorerie de démarrage.', (string) Str::ulid(), $first);
        $this->assertSame(CapitalContributionState::EnAttente, $contribution->state);
        $this->assertDatabaseCount('contribution_shares', 0);

        try {
            $service->approve($contribution, $first);
            $this->fail('Un directeur ne peut pas approuver son propre apport.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('capital_contribution', $exception->errors());
        }
        $this->assertDatabaseCount('contribution_shares', 0);

        $service->approve($contribution, $second);

        $this->assertSame(CapitalContributionState::Approuve, $contribution->refresh()->state);
        $this->assertDatabaseHas('contribution_shares', [
            'holder_id' => $first->getKey(),
            'origin' => ContributionOrigin::Apport->value,
            'base_amount' => 2_000_000,
            'coefficient_basis_points' => 20_000,
            'issued_shares' => 4_000_000,
        ]);
        $this->assertSame(4_000_000, app(ContributionShareService::class)->totalFor($first));
    }

    /** AC 10 — aucune écriture de remboursement n'existe : ni méthode de service, ni route. */
    public function test_ac_10_no_refund_path_exists_anywhere_in_the_system(): void
    {
        $methods = get_class_methods(CapitalContributionService::class);

        foreach ($methods as $method) {
            $this->assertStringNotContainsStringIgnoringCase('rembours', $method);
            $this->assertStringNotContainsStringIgnoringCase('refund', $method);
        }

        $this->assertFalse(in_array('rembourse', array_column(
            CapitalContributionState::cases(),
            'value',
        ), true), 'Aucun état de remboursement ne doit exister.');
    }

    public function test_ac_11_a_refusal_is_motivated_and_recorded(): void
    {
        $first = $this->director();
        $second = $this->director();
        $service = app(CapitalContributionService::class);
        $contribution = $service->record(500_000, 'Avance de trésorerie.', (string) Str::ulid(), $first);

        try {
            $service->refuse($contribution, '   ', $second);
            $this->fail('Un refus sans motif doit être rejeté.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refusal_reason', $exception->errors());
        }

        $service->refuse($contribution, 'Trésorerie déjà suffisante pour le trimestre.', $second);

        $this->assertSame(CapitalContributionState::Refuse, $contribution->refresh()->state);
        $this->assertSame('Trésorerie déjà suffisante pour le trimestre.', $contribution->refusal_reason);
        $this->assertSame((int) $second->getKey(), (int) $contribution->refused_by);
        $this->assertDatabaseCount('contribution_shares', 0);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => CapitalContribution::class,
            'auditable_id' => $contribution->getKey(),
            'action' => 'capital_contribution_refused',
        ]);
    }

    /** AC 12 et AC 15 — le coefficient est celui de la date de l'événement, et il est figé. */
    public function test_ac_12_and_15_the_coefficient_is_frozen_on_the_line_and_a_new_scale_is_not_retroactive(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $this->travelTo(now('UTC')->setDate(2027, 10, 15)->setTime(9, 0));

        $payment = $this->record($actor, $client, $contract, $account, 200_000);
        $entry = ContributionShare::query()->where('payment_id', $payment->getKey())->firstOrFail();

        $this->assertSame(2, $entry->vintage_year);
        $this->assertSame(15_000, $entry->coefficient_basis_points);
        $this->assertSame(135_000, $entry->issued_shares);

        config()->set('contribution-shares.coefficients', [1 => 50_000, 2 => 50_000]);

        $this->assertSame(15_000, $entry->refresh()->coefficient_basis_points);
        $this->assertSame(135_000, $entry->issued_shares);
        $this->assertSame(135_000, app(ContributionShareService::class)->totalFor($director));
    }

    /** AC 14 et AC 19 — le pourcentage se calcule à la lecture et la dilution se constate. */
    public function test_ac_14_and_19_percentages_are_read_time_and_a_new_contribution_dilutes_without_reducing(): void
    {
        $first = $this->director();
        $second = $this->director();
        $service = app(CapitalContributionService::class);
        $shares = app(ContributionShareService::class);

        $firstContribution = $service->record(1_000_000, 'Apport initial.', (string) Str::ulid(), $first);
        $service->approve($firstContribution, $second);

        $register = $shares->register();
        $this->assertSame('100,00 %', $this->blockFor($register, $first)['percentage_label']);
        // Un directeur sans contribution est à zéro pour cent, et le registre le dit : l'absence
        // de ligne n'est pas une absence d'information.
        $this->assertSame('0,00 %', $this->blockFor($register, $second)['percentage_label']);

        $this->travelTo(now('UTC')->setDate(2026, 12, 1)->setTime(9, 0));
        $secondContribution = $service->record(3_000_000, 'Apport de renfort.', (string) Str::ulid(), $second);
        $service->approve($secondContribution, $first);

        $register = $shares->register();
        $firstBlock = $this->blockFor($register, $first);
        $secondBlock = $this->blockFor($register, $second);

        $this->assertSame(2_000_000, $firstBlock['shares'], 'Les parts acquises ne décroissent jamais.');
        $this->assertSame(6_000_000, $secondBlock['shares']);
        $this->assertSame('25,00 %', $firstBlock['percentage_label']);
        $this->assertSame('75,00 %', $secondBlock['percentage_label']);
        $this->assertSame(
            ['100,00 %', '25,00 %'],
            array_column($firstBlock['timeline'], 'percentage_label'),
            'La trajectoire doit montrer la dilution du premier directeur.',
        );
    }

    public function test_ac_16_and_17_each_line_states_its_origin_amount_vintage_coefficient_and_shares(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $payment = $this->record($actor, $client, $contract, $account, 200_000);

        $line = $this->blockFor(app(ContributionShareService::class)->register(), $director)['entries'][0];

        $this->assertSame(ContributionOrigin::Encaissement->value, $line['origin']);
        $this->assertStringContainsString($contract->reference, $line['source_label']);
        $this->assertStringContainsString($client->name, $line['source_label']);
        $this->assertSame($payment->receipt_number, $line['receipt_number']);
        $this->assertSame('Année 1', $line['vintage_label']);
        $this->assertSame('× 2', $line['coefficient_label']);
        $this->assertSame(180_000, $line['issued_shares']);
        $this->assertStringContainsString('90', $line['base_amount_label']);
    }

    public function test_ac_20_issuance_and_reversal_are_audited_with_their_author_and_origin(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $payment = $this->record($actor, $client, $contract, $account, 200_000);
        $emission = ContributionShare::query()->where('payment_id', $payment->getKey())->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ContributionShare::class,
            'auditable_id' => $emission->getKey(),
            'action' => 'contribution_shares_issued_from_payment',
            'actor_id' => $actor->getKey(),
        ]);

        app(PaymentService::class)->cancel($payment, 'Erreur de saisie.', (string) Str::ulid(), $actor);
        $reversal = ContributionShare::query()->where('entry_type', ContributionEntryType::Annulation->value)->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ContributionShare::class,
            'auditable_id' => $reversal->getKey(),
            'action' => 'contribution_shares_reversed',
            'actor_id' => $actor->getKey(),
        ]);
    }

    /** AC 21 — la base elle-même refuse la modification et la suppression d'une part émise. */
    public function test_ac_21_an_issued_share_can_neither_be_updated_nor_deleted_in_the_database(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $payment = $this->record($actor, $client, $contract, $account, 200_000);
        $entry = ContributionShare::query()->where('payment_id', $payment->getKey())->firstOrFail();

        $this->assertTrue(
            $this->refuses(fn () => DB::table('contribution_shares')->where('id', $entry->getKey())->update(['issued_shares' => 1])),
            'Le déclencheur doit refuser toute modification d’une part émise.',
        );
        $this->assertTrue(
            $this->refuses(fn () => DB::table('contribution_shares')->where('id', $entry->getKey())->delete()),
            'Le déclencheur doit refuser toute suppression physique d’une part.',
        );
        $this->assertDatabaseHas('contribution_shares', ['id' => $entry->getKey(), 'issued_shares' => 180_000]);
    }

    /** AC 10 et AC 21 — un apport tranché se fige aussi au niveau de la base. */
    public function test_ac_10_a_settled_capital_contribution_can_no_longer_be_rewritten(): void
    {
        $first = $this->director();
        $second = $this->director();
        $service = app(CapitalContributionService::class);
        $contribution = $service->record(750_000, 'Apport définitif.', (string) Str::ulid(), $first);
        $service->approve($contribution, $second);

        $this->assertTrue(
            $this->refuses(fn () => DB::table('capital_contributions')->where('id', $contribution->getKey())->update([
                'state' => CapitalContributionState::EnAttente->value,
            ])),
            'Un apport approuvé est définitif : la base doit refuser sa réécriture.',
        );
        $this->assertDatabaseHas('capital_contributions', [
            'id' => $contribution->getKey(), 'state' => CapitalContributionState::Approuve->value,
        ]);
    }

    public function test_ac_23_the_invariant_passes_on_a_consistent_ledger_and_reports_a_gap(): void
    {
        $director = $this->director();
        [$actor, $client, $contract, $account] = $this->context($director, hasExecution: false);
        $this->record($actor, $client, $contract, $account, 200_000);
        $invariant = app(ContributionShareIntegrityInvariant::class);

        $this->assertTrue($invariant->check()->passed);

        // Une part entreprise sur un contrat à apporteur directeur, sans ligne au registre : c'est
        // exactement l'écart que l'invariant doit voir.
        $orphan = Payment::factory()->create([
            'client_id' => $client->getKey(), 'contract_id' => $contract->getKey(), 'account_id' => $account->getKey(),
        ]);
        ShareEntitlement::factory()->create([
            'contract_id' => $contract->getKey(),
            'payment_id' => $orphan->getKey(),
            'beneficiary_id' => null,
            'beneficiary_key' => 'company:ptr-niger',
            'share_type' => ShareType::PtrNiger->value,
            'base_amount' => 50_000,
            'share_amount' => 50_000,
        ]);

        $result = $invariant->check();
        $this->assertFalse($result->passed);
        $this->assertStringContainsString('90000', str_replace(' ', '', $result->observed));
    }

    /** @param array{total_shares: int, generated_at: string, method: string, directors: list<array<string, mixed>>} $register
     * @return array<string, mixed> */
    private function blockFor(array $register, User $director): array
    {
        foreach ($register['directors'] as $block) {
            if ((int) $block['id'] === (int) $director->getKey()) {
                return $block;
            }
        }

        $this->fail("Le registre doit présenter le directeur #{$director->getKey()}.");
    }

    private function refuses(callable $operation): bool
    {
        try {
            $operation();
        } catch (Throwable) {
            return true;
        }

        return false;
    }

    /** @return array{0: User, 1: Client, 2: Contract, 3: Account} */
    private function context(?User $contributor, bool $hasExecution): array
    {
        $actor = User::factory()->active()->create();
        $client = Client::factory()->create();
        $contract = Contract::factory()->create([
            'client_id' => $client->getKey(),
            'expected_total_amount' => 1_000_000,
            'forecast_profit_amount' => 500_000,
            'contributor_id' => $contributor?->getKey(),
            'has_execution' => $hasExecution,
        ]);
        $account = Account::factory()->create(['opening_balance_amount' => 1_000]);

        return [$actor, $client, $contract, $account];
    }

    private function record(User $actor, Client $client, Contract $contract, Account $account, int $amount): Payment
    {
        return app(PaymentService::class)->record([
            'client_id' => (int) $client->getKey(),
            'contract_id' => (int) $contract->getKey(),
            'project_id' => null,
            'account_id' => (int) $account->getKey(),
            'received_amount' => $amount,
            'received_at' => now('Africa/Niamey')->format('Y-m-d\\TH:i'),
            'payment_mode' => 'especes',
            'reference' => 'REF-12-1',
            'attachment_ulid' => null,
            'idempotency_key' => (string) Str::ulid(),
        ], $actor);
    }

    private function director(): User
    {
        return $this->userWithRole('direction');
    }

    private function employee(): User
    {
        return $this->userWithRole('employe');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test registre des parts 12.1');

        return $user;
    }

    /** Le service refuse un non-directeur avant même d'atteindre la base. */
    public function test_ac_7_an_employee_cannot_record_a_capital_contribution(): void
    {
        $this->expectException(AuthorizationException::class);

        app(CapitalContributionService::class)->record(100_000, 'Tentative.', (string) Str::ulid(), $this->employee());
    }
}
