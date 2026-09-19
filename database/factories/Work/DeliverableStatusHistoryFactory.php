<?php

namespace Database\Factories\Work;

use App\Enums\DeliverableStatus;
use App\Models\Identity\User;
use App\Models\Work\Deliverable;
use App\Models\Work\DeliverableStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliverableStatusHistory>
 */
class DeliverableStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deliverable_id' => Deliverable::factory(), 'from_status' => DeliverableStatus::Prevu,
            'to_status' => DeliverableStatus::Soumis, 'actor_id' => User::factory(),
            'reason' => 'Livrable soumis pour validation.', 'changed_at' => now('UTC'),
        ];
    }
}
