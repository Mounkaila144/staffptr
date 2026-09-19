<?php

namespace Database\Factories\Work;

use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\WorkComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkComment>
 */
class WorkCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commentable_type' => Objective::class, 'commentable_id' => Objective::factory(),
            'author_id' => User::factory(), 'body' => fake()->sentence(), 'correction_requested' => false,
        ];
    }
}
