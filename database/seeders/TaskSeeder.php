<?php

namespace Database\Seeders;

use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Models\Work\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->where('state', 'actif')->orderBy('id')->first();
        if (! $user instanceof User) {
            return;
        }
        Task::query()->updateOrCreate(['assignee_id' => $user->getKey(), 'title' => 'Consulter les objectifs du mois', 'due_date' => '2026-08-10'], ['created_by' => $user->getKey(), 'project_id' => Project::query()->value('id'), 'objective_id' => Objective::query()->value('id'), 'priority' => WorkPriority::Normale, 'status' => WorkTaskStatus::AFaire]);
    }
}
