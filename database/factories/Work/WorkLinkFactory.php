<?php

namespace Database\Factories\Work;

use App\Models\Identity\User;
use App\Models\Work\Project;
use App\Models\Work\WorkLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLink>
 */
class WorkLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linkable_type' => Project::class, 'linkable_id' => Project::factory(),
            'label' => 'Document de référence', 'url' => 'https://example.test/document', 'created_by' => User::factory(),
        ];
    }
}
