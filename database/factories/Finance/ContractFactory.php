<?php

namespace Database\Factories\Finance;

use App\Enums\ContractState;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'project_id' => null,
            'reference' => 'CTR-'.fake()->unique()->numerify('########'),
            'title' => fake()->sentence(4),
            'expected_total_amount' => fake()->numberBetween(100_000, 20_000_000),
            'forecast_profit_amount' => fake()->numberBetween(50_000, 10_000_000),
            'contributor_id' => null,
            'has_execution' => false,
            'state' => ContractState::Active->value,
            'starts_on' => fake()->date(),
            'ends_on' => null,
            'closed_at' => null,
            'closure_reason' => null,
            'cancellation_reason' => null,
        ];
    }
}
