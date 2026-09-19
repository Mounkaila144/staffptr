<?php

namespace Database\Factories\Platform;

use App\Models\Platform\InternalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalDocument>
 */
class InternalDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'requires_acknowledgement' => true,
            'current_version_id' => null,
        ];
    }
}
