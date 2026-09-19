<?php

namespace Tests\Feature;

use App\Enums\BlockerState;
use App\Enums\BlockerUrgency;
use App\Enums\DailyReportState;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;
use App\Models\Work\Task;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AccountabilityModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_report_identity_exists_per_author_and_civil_date(): void
    {
        $author = User::factory()->create();
        DailyReport::factory()->create([
            'author_id' => $author->getKey(),
            'report_date' => '2026-08-11',
        ]);

        $this->expectException(QueryException::class);

        DailyReport::factory()->create([
            'author_id' => $author->getKey(),
            'report_date' => '2026-08-11',
        ]);
    }

    public function test_report_keeps_several_numbered_immutable_versions(): void
    {
        $author = User::factory()->create();
        $report = DailyReport::factory()->create(['author_id' => $author->getKey()]);
        $first = DailyReportVersion::factory()->create([
            'daily_report_id' => $report->getKey(),
            'author_id' => $author->getKey(),
            'version_number' => 1,
        ]);
        DailyReportVersion::factory()->create([
            'daily_report_id' => $report->getKey(),
            'author_id' => $author->getKey(),
            'version_number' => 2,
        ]);

        $this->assertSame(2, $report->versions()->count());
        $this->assertSame(2, $report->currentVersion()->firstOrFail()->version_number);

        $this->expectException(LogicException::class);
        $first->update(['planned_task' => 'Texte remplacé']);
    }

    public function test_report_and_blocker_states_are_typed_and_physical_deletion_is_forbidden(): void
    {
        $report = DailyReport::factory()->submitted()->create();
        $task = Task::factory()->create();
        $blocker = Blocker::factory()->create([
            'origin_type' => $task->getMorphClass(),
            'origin_id' => $task->getKey(),
            'urgency' => BlockerUrgency::Urgente,
            'state' => BlockerState::PrisEnCharge,
        ]);

        $this->assertSame(DailyReportState::Envoye, $report->state);
        $this->assertSame(BlockerUrgency::Urgente, $blocker->urgency);
        $this->assertSame(BlockerState::PrisEnCharge, $blocker->state);
        $this->assertTrue($blocker->origin->is($task));

        $this->expectException(LogicException::class);
        $blocker->delete();
    }
}
