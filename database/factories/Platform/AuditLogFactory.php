<?php

namespace Database\Factories\Platform;

use App\Models\Platform\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'actor_label' => 'Test automatisé',
            'occurred_at' => now('UTC'),
            'auditable_type' => 'tests.fixture',
            'auditable_id' => fake()->numberBetween(1, 100_000),
            'action' => 'created',
            'old_values' => null,
            'new_values' => ['status' => 'created'],
            'reason' => null,
            'ip_address' => inet_pton('127.0.0.1'),
            'user_agent' => 'PHPUnit',
            'request_id' => fake()->uuid(),
        ];
    }
}
