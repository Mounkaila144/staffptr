<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountState;
use App\Enums\FinancialAccountType;
use App\Models\Finance\Account;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FinancialAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->accounts() as $label => $type) {
            $existing = Account::query()->firstOrNew(['label' => $label]);

            Account::query()->updateOrCreate(
                ['label' => $label],
                [
                    'type' => $type->value,
                    'opening_balance_amount' => $existing->exists
                        ? (int) $existing->getRawOriginal('opening_balance_amount')
                        : 0,
                    'opening_balance_date' => $existing->exists
                        ? (string) $existing->getRawOriginal('opening_balance_date')
                        : CarbonImmutable::parse('2026-08-17', 'Africa/Niamey')->toDateString(),
                    'state' => $existing->exists
                        ? (string) $existing->getRawOriginal('state')
                        : FinancialAccountState::Active->value,
                    'deactivation_reason' => $existing->exists
                        ? $existing->getRawOriginal('deactivation_reason')
                        : null,
                    'deactivated_at' => $existing->exists
                        ? $existing->getRawOriginal('deactivated_at')
                        : null,
                ],
            );
        }
    }

    /** @return array<string, FinancialAccountType> */
    private function accounts(): array
    {
        return [
            'Caisse principale' => FinancialAccountType::Caisse,
            'Mobile Money PTR-Niger' => FinancialAccountType::MobileMoney,
        ];
    }
}
