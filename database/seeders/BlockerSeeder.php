<?php

namespace Database\Seeders;

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Models\Accountability\Blocker;
use App\Models\Identity\User;
use App\Models\Work\Task;
use Illuminate\Database\Seeder;

class BlockerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $task = Task::query()->with('assignee.manager')->orderBy('id')->first();

        if (! $task instanceof Task || ! $task->assignee instanceof User || ! $task->assignee->manager instanceof User) {
            return;
        }

        Blocker::query()->updateOrCreate(
            [
                'origin_type' => $task->getMorphClass(),
                'origin_id' => $task->getKey(),
                'created_by' => $task->assignee_id,
            ],
            [
                'solicited_user_id' => $task->assignee->manager->getKey(),
                'problem' => 'Une information nécessaire manque pour poursuivre.',
                'urgency' => BlockerUrgency::Normale,
                'reported_on' => '2026-08-11',
                'deadline_impact' => "L'échéance peut être décalée d'une journée.",
                'attempted_action' => "J'ai consulté les documents disponibles.",
                'state' => BlockerState::Ouvert,
            ],
        );
    }
}
