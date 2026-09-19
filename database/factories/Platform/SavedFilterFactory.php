<?php

namespace Database\Factories\Platform;

use App\Models\Identity\User;
use App\Models\Platform\SavedFilter;
use App\Support\Listing\ListRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedFilter>
 */
class SavedFilterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'list_key' => ListRegistry::EXPENSES,
            'name' => 'Filtre '.fake()->unique()->numerify('###'),
            'criteria' => ['state' => 'demandee'],
            'is_active' => true,
            'deactivated_at' => null,
        ];
    }

    public function forList(string $listKey): self
    {
        return $this->state(fn (array $attributes): array => ['list_key' => $listKey]);
    }

    public function deactivated(): self
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'deactivated_at' => now('UTC'),
        ]);
    }
}
