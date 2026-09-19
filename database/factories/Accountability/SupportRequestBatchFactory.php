<?php

namespace Database\Factories\Accountability;

use App\Models\Accountability\SupportRequestBatch;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupportRequestBatch>
 */
class SupportRequestBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tutor_id' => User::factory(),
            'scheduled_for' => CarbonImmutable::now('UTC')->addDay(),
            'delivered_at' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function delivered(): self
    {
        return $this->state(fn (array $attributes): array => [
            'delivered_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
