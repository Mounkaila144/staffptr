<?php

namespace Tests\Feature;

use App\Enums\FinancialAccountState;
use App\Enums\FinancialMovementDirection;
use App\Models\Finance\Account;
use App\Models\Finance\AccountMovement;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Finance\AccountService;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\Support\IdentityTestCase;

class FinancialAccountLifecycleTest extends IdentityTestCase
{
    public function test_ac_2_3_37_and_96_balance_is_calculated_from_initial_balance_and_validated_movements(): void
    {
        $account = Account::factory()->create(['opening_balance_amount' => 100_000]);
        AccountMovement::factory()->for($account)->create([
            'direction' => FinancialMovementDirection::Credit->value,
            'movement_amount' => 50_000,
        ]);
        AccountMovement::factory()->for($account)->create([
            'direction' => FinancialMovementDirection::Debit->value,
            'movement_amount' => 20_000,
        ]);

        $this->assertSame(130_000, $account->currentBalance());
    }

    public function test_ac_5_creating_a_cash_or_mobile_money_account_emits_no_external_call(): void
    {
        Http::fake();
        $actor = User::factory()->active()->create();

        app(AccountService::class)->create([
            'type' => 'mobile_money',
            'label' => 'Mobile Money local',
            'opening_balance_amount' => 0,
            'opening_balance_date' => '2026-08-17',
        ], $actor);

        Http::assertNothingSent();
    }

    public function test_ac_6_deactivation_is_motivated_audited_and_does_not_delete_the_account(): void
    {
        $actor = User::factory()->active()->create();
        $account = Account::factory()->create();

        app(AccountService::class)->deactivate($account, 'Compte remplacé après changement de caisse.', $actor);

        $account->refresh();
        $this->assertSame(FinancialAccountState::Inactive, $account->state);
        $this->assertSame('Compte remplacé après changement de caisse.', $account->deactivation_reason);
        $this->assertDatabaseHas('accounts', ['id' => $account->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Account::class,
            'auditable_id' => $account->getKey(),
            'action' => 'financial_account_deactivated',
        ]);

        $this->expectException(LogicException::class);
        $account->delete();
    }

    public function test_account_creation_and_deactivation_audits_are_written_in_the_business_transaction(): void
    {
        $actor = User::factory()->active()->create();
        $account = app(AccountService::class)->create([
            'type' => 'caisse',
            'label' => 'Caisse terrain',
            'opening_balance_amount' => 25_000,
            'opening_balance_date' => '2026-08-17',
        ], $actor);

        $this->assertSame([
            'financial_account_created',
        ], AuditLog::query()
            ->where('auditable_type', Account::class)
            ->where('auditable_id', $account->getKey())
            ->pluck('action')
            ->all());
    }
}
