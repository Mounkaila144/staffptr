<?php

namespace Database\Factories\Work;

use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\ObjectiveVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObjectiveVersion>
 */
class ObjectiveVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'objective_id' => Objective::factory(), 'version_number' => 2,
            'previous_values' => ['title' => 'Ancienne valeur'], 'new_values' => ['title' => 'Nouvelle valeur'],
            'reason' => 'Correction demandée après validation.', 'author_id' => User::factory(),
        ];
    }
}
