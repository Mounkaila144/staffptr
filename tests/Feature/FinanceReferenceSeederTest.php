<?php

namespace Tests\Feature;

use App\Enums\FinancialAccountType;
use App\Models\Finance\Account;
use App\Models\Finance\FixedCharge;
use Database\Seeders\FinancialAccountSeeder;
use Database\Seeders\FixedChargeSeeder;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;

class FinanceReferenceSeederTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    public function test_ac_7_and_dec_09_seeders_are_exact_and_idempotent(): void
    {
        $this->seed(FinancialAccountSeeder::class);
        $this->seed(FixedChargeSeeder::class);
        $this->seed(FinancialAccountSeeder::class);
        $this->seed(FixedChargeSeeder::class);

        $this->assertSame([
            'Caisse principale',
            'Mobile Money PTR-Niger',
        ], Account::query()->orderBy('id')->pluck('label')->all());
        $this->assertSame([
            'Loyer',
            'Électricité',
            'Internet',
            'Salaires',
        ], FixedCharge::query()->orderBy('id')->pluck('label')->all());

        $this->assertSame(
            FinancialAccountType::MobileMoney,
            Account::query()->where('label', 'Mobile Money PTR-Niger')->firstOrFail()->type,
        );
    }

    public function test_reference_seeders_preserve_configured_financial_values_on_replay(): void
    {
        $this->seed(FinancialAccountSeeder::class);
        $this->seed(FixedChargeSeeder::class);

        $account = Account::query()->where('label', 'Caisse principale')->firstOrFail();
        $account->forceFill(['opening_balance_amount' => 450_000])->saveOrFail();
        $charge = FixedCharge::query()->where('label', 'Loyer')->firstOrFail();
        $charge->forceFill(['monthly_amount' => 275_000])->saveOrFail();

        $this->seed(FinancialAccountSeeder::class);
        $this->seed(FixedChargeSeeder::class);

        $this->assertSame(450_000, $account->fresh()->opening_balance_amount);
        $this->assertSame(275_000, $charge->fresh()->monthly_amount);
    }
}
