<?php

namespace Database\Factories\Finance;

use App\Enums\CorrectionPlanState;
use App\Models\Finance\CorrectionPlan;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectionPlan>
 */
class CorrectionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now('Africa/Niamey')->startOfMonth()->toDateString(),
            'version' => 1,
            'previous_id' => null,
            'finding' => "Les encaissements du mois sont restés sous l'assiette des charges fixes.",
            'actions' => 'Relancer les factures échues et suspendre les engagements non essentiels.',
            'responsibles' => 'Direction et responsable financier',
            'due_on' => now('Africa/Niamey')->addDays(14)->toDateString(),
            'expected_result' => "Retour des encaissements au niveau de l'assiette le mois suivant.",
            'state' => CorrectionPlanState::Brouillon->value,
            'created_by' => User::factory(),
            'validated_by' => null,
            'validated_at' => null,
            'revision_reason' => null,
        ];
    }

    /** Plan validé : figé, plus aucune modification directe n'est possible (AC 17). */
    public function validated(): self
    {
        return $this->state(fn (array $attributes): array => [
            'state' => CorrectionPlanState::Valide->value,
            'validated_by' => User::factory(),
            'validated_at' => now('UTC'),
        ]);
    }
}
