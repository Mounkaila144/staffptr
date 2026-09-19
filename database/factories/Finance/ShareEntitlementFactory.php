<?php

namespace Database\Factories\Finance;

use App\Enums\ShareType;
use App\Models\Finance\Contract;
use App\Models\Finance\Payment;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShareEntitlement>
 */
class ShareEntitlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $beneficiary = User::factory();

        return [
            'contract_id' => Contract::factory(),
            'payment_id' => Payment::factory(),
            'beneficiary_id' => $beneficiary,
            'beneficiary_key' => 'user:'.fake()->unique()->numberBetween(1, 2_000_000_000),
            'share_type' => ShareType::Contributor->value,
            'base_amount' => 100_000,
            'rate_basis_points' => 1_000,
            'rate_divisor' => 1,
            'share_amount' => 10_000,
            'paid_amount' => 0,
            'calculation_method' => 'Base explicite × taux en points de base.',
            'period_start' => now('Africa/Niamey')->startOfMonth()->toDateString(),
            'period_end' => now('Africa/Niamey')->endOfMonth()->toDateString(),
            'source_received_on' => now('Africa/Niamey')->toDateString(),
        ];
    }
}
