<?php

namespace Database\Factories\Platform;

use App\Models\Identity\User;
use App\Models\Platform\InternalDocumentAcknowledgement;
use App\Models\Platform\InternalDocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalDocumentAcknowledgement>
 */
class InternalDocumentAcknowledgementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internal_document_version_id' => InternalDocumentVersion::factory(),
            'user_id' => User::factory()->active(),
            'acknowledged_at' => now('UTC'),
        ];
    }
}
