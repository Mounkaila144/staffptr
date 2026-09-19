<?php

namespace Database\Seeders;

use App\Enums\DailyReportState;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use Illuminate\Database\Seeder;

class DailyReportSeeder extends Seeder
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

        $report = DailyReport::query()->updateOrCreate(
            ['author_id' => $user->getKey(), 'report_date' => '2026-08-11'],
            ['state' => DailyReportState::Brouillon],
        );

        DailyReportVersion::query()->firstOrCreate(
            ['daily_report_id' => $report->getKey(), 'version_number' => 1],
            [
                'author_id' => $user->getKey(),
                'idempotency_key' => '24c77ad4-a81c-4d4c-87f2-412045fcb901',
                'planned_task' => 'Finaliser les priorités de la journée',
                'achieved_result' => 'Les priorités ont été vérifiées.',
                'evidence_link' => 'https://example.test/preuve',
                'blocker_present' => false,
                'next_action' => 'Préparer la prochaine livraison.',
                'help_requested' => false,
            ],
        );
    }
}
