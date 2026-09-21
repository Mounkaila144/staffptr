<?php

namespace Database\Factories\Finance;

use App\Enums\CapitalContributionState;
use App\Models\Finance\CapitalContribution;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CapitalContribution>
 */
class CapitalContributionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contributor_id' => User::factory(),
            'contribution_amount' => fake()->numberBetween(100_000, 5_000_000),
            'declared_on' => now('Africa/Niamey')->toDateString(),
            'state' => CapitalContributionState::EnAttente->value,
            'purpose' => 'Renforcement de la trésorerie de démarrage.',
            'approved_by' => null,
            'approved_at' => null,
            'refused_by' => null,
            'refused_at' => null,
            'refusal_reason' => null,
            'idempotency_key' => (string) Str::ulid(),
        ];
    }

    /** @return $this */
    public function approved(?User $approver = null): self
    {
        return $this->state(fn (): array => [
            'state' => CapitalContributionState::Approuve->value,
            'approved_by' => $approver?->getKey() ?? User::factory(),
            'approved_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    /** @return $this */
    public function refused(?User $refuser = null): self
    {
        return $this->state(fn (): array => [
            'state' => CapitalContributionState::Refuse->value,
            'refused_by' => $refuser?->getKey() ?? User::factory(),
            'refused_at' => CarbonImmutable::now('UTC'),
            'refusal_reason' => 'Trésorerie déjà suffisante pour le trimestre.',
        ]);
    }
}
