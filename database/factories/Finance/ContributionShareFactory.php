<?php

namespace Database\Factories\Finance;

use App\Enums\ContributionEntryType;
use App\Enums\ContributionOrigin;
use App\Models\Finance\ContributionShare;
use App\Models\Identity\User;
use App\Support\VintageCoefficient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContributionShare>
 */
class ContributionShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseAmount = fake()->numberBetween(100_000, 5_000_000);

        return [
            'holder_id' => User::factory(),
            'origin' => ContributionOrigin::Apport->value,
            'entry_type' => ContributionEntryType::Emission->value,
            'share_entitlement_id' => null,
            'payment_id' => null,
            'contract_id' => null,
            'capital_contribution_id' => null,
            'reversal_of_id' => null,
            'base_amount' => $baseAmount,
            'vintage_year' => 1,
            'coefficient_basis_points' => 20_000,
            'issued_shares' => intdiv($baseAmount * 20_000, VintageCoefficient::NEUTRAL_BASIS_POINTS),
            'occurred_on' => now('Africa/Niamey')->toDateString(),
            'reason' => 'Apport d’argent personnel approuvé par le second directeur.',
            'created_by' => User::factory(),
        ];
    }
}
