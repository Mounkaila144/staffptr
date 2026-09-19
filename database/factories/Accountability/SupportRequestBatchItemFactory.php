<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\Blocker;
use App\Models\Accountability\SupportRequestBatch;
use App\Models\Accountability\SupportRequestBatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequestBatchItem>
 */
class SupportRequestBatchItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_request_batch_id' => SupportRequestBatch::factory(),
            'blocker_id' => Blocker::factory(),
        ];
    }
}
