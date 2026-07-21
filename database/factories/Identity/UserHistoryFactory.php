<?php

namespace Database\Factories\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserHistory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserHistory>
 */
class UserHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->active(),
            'field' => 'department_id',
            'old_value' => 'Conseil',
            'new_value' => 'Technique',
            'changed_by' => User::factory()->active(),
            'changed_at' => CarbonImmutable::now('UTC'),
            'reason' => null,
        ];
    }
}
