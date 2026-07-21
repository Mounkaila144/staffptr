<?php

namespace Database\Factories\Identity;

use App\Enums\DocumentType;
use App\Models\Identity\Person;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonDocument>
 */
class PersonDocumentFactory extends Factory
{
    protected $model = PersonDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'document_type' => DocumentType::Contrat,
            'archived_at' => null,
            'archive_reason' => null,
            'uploaded_by' => User::factory()->active(),
        ];
    }

    public function archived(string $reason = 'Document remplacé par une version actualisée.'): static
    {
        return $this->state(fn (): array => [
            'archived_at' => CarbonImmutable::now('UTC'),
            'archive_reason' => $reason,
        ]);
    }
}
