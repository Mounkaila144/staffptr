<?php

namespace Database\Seeders;

use App\Enums\DeliverableStatus;
use App\Models\Identity\User;
use App\Models\Work\Deliverable;
use App\Models\Work\Project;
use Illuminate\Database\Seeder;

class DeliverableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $project = Project::query()->first();
        $owner = User::query()->where('state', 'actif')->orderBy('id')->first();
        if (! $project instanceof Project || ! $owner instanceof User) {
            return;
        }
        Deliverable::query()->updateOrCreate(['project_id' => $project->getKey(), 'title' => 'Synthèse mensuelle'], ['owner_id' => $owner->getKey(), 'planned_date' => '2026-08-31', 'status' => DeliverableStatus::Prevu]);
    }
}
