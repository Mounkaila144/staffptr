<?php

namespace Database\Factories\Finance;

use App\Models\Finance\Contract;
use App\Models\Finance\ContractExecutor;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractExecutor>
 */
class ContractExecutorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'user_id' => User::factory(),
            'position' => 1,
            'is_active' => true,
            'deactivated_at' => null,
        ];
    }
}
