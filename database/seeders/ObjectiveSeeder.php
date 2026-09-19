<?php

namespace Database\Seeders;

use App\Enums\ObjectiveState;
use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use Illuminate\Database\Seeder;

class ObjectiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->where('state', 'actif')->orderBy('id')->first();
        if (! $owner instanceof User) {
            return;
        }
        Objective::query()->updateOrCreate(['user_id' => $owner->getKey(), 'title' => 'Formaliser les engagements du mois', 'due_date' => '2026-08-31'], ['created_by' => $owner->getKey(), 'company_priority_id' => CompanyPriority::query()->value('id'), 'project_id' => Project::query()->value('id'), 'description' => 'Définir et suivre les résultats attendus.', 'indicator' => 'Objectifs documentés', 'target_value' => '3', 'expected_evidence' => 'Capture ou document de synthèse', 'required_means' => 'Accès à PTR Staff', 'priority' => WorkPriority::Normale, 'state' => ObjectiveState::Brouillon, 'progress' => 0]);
    }
}
