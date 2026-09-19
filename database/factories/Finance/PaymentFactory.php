<?php

namespace Database\Factories\Finance;

use App\Enums\PaymentMode;
use App\Enums\PaymentState;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'REC-'.fake()->unique()->numerify('########'),
            'client_id' => Client::factory(),
            'contract_id' => null,
            'project_id' => null,
            'invoice_id' => null,
            'account_id' => Account::factory(),
            'received_amount' => fake()->numberBetween(1_000, 5_000_000),
            'received_on' => now('Africa/Niamey')->toDateString(),
            'payment_mode' => PaymentMode::Especes->value,
            'reference' => fake()->optional()->bothify('REF-####'),
            'state' => PaymentState::Validated->value,
            'correction_of_id' => null,
            'reversal_of_id' => null,
            'correction_reason' => null,
            'cancellation_reason' => null,
            'late_recording' => false,
            'post_reopening' => false,
            'idempotency_key' => (string) Str::ulid(),
            'created_by' => User::factory(),
        ];
    }
}
