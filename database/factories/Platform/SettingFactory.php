<?php

namespace Database\Factories\Platform;

use App\Models\Platform\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->lexify('custom_????????'),
            'value' => fake()->word(),
            'type' => 'string',
            'effective_at' => CarbonImmutable::now('UTC'),
        ];
    }
}
