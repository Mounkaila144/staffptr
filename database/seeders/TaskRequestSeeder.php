<?php

namespace Database\Seeders;

use App\Enums\TaskRequestState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\TaskRequest;
use App\Models\Identity\User;
use Illuminate\Database\Seeder;

class TaskRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $report = DailyReport::query()->with('author')->orderBy('id')->first();

        if (! $report instanceof DailyReport || ! $report->author instanceof User || ! $report->author->manager instanceof User) {
            return;
        }

        TaskRequest::query()->updateOrCreate(
            ['daily_report_id' => $report->getKey(), 'requested_by' => $report->author_id],
            [
                'responsible_id' => $report->author->manager->getKey(),
                'description' => 'Merci de me proposer une nouvelle tâche prioritaire.',
                'is_urgent' => false,
                'state' => TaskRequestState::Ouvert,
            ],
        );
    }
}
