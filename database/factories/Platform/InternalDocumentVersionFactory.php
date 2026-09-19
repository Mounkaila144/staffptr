<?php

namespace Database\Factories\Platform;

use App\Models\Identity\User;
use App\Models\Platform\InternalDocument;
use App\Models\Platform\InternalDocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalDocumentVersion>
 */
class InternalDocumentVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internal_document_id' => InternalDocument::factory(),
            'version_number' => 1,
            'body' => fake()->paragraphs(4, true),
            'effective_date' => today('Africa/Niamey')->toDateString(),
            'published_by' => User::factory()->active(),
            'published_at' => now('UTC'),
        ];
    }
}
