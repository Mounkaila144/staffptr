<?php

namespace Database\Factories\Finance;

use App\Enums\InvoiceState;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'contract_id' => Contract::factory(),
            'number' => 'FAC-'.fake()->unique()->numerify('########'),
            'total_amount' => fake()->numberBetween(10_000, 10_000_000),
            'issued_on' => fake()->date(),
            'due_on' => fake()->dateTimeBetween('now', '+90 days')->format('Y-m-d'),
            'state' => InvoiceState::Impayee->value,
            'cancellation_reason' => null,
            'cancelled_at' => null,
        ];
    }
}
