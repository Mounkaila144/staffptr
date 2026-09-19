<?php

namespace Database\Factories\Accountability;

use App\Enums\InternshipChecklistType;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipChecklistItem>
 */
class InternshipChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internship_id' => Internship::factory(),
            'checklist_type' => InternshipChecklistType::Integration,
            'position' => 1,
            'label' => InternshipChecklistType::Integration->items()[0],
            'completed_by' => null,
            'completed_at' => null,
        ];
    }

    public function exit(): self
    {
        return $this->state(fn (array $attributes): array => [
            'checklist_type' => InternshipChecklistType::Sortie,
            'label' => InternshipChecklistType::Sortie->items()[0],
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
