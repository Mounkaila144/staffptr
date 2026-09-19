<?php

namespace Tests\Feature;

use App\Enums\MonthlyReportState;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\MonthClosure;
use App\Models\Identity\User;
use App\Services\Finance\MonthGuard;
use App\Services\Finance\MonthlyReportService;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Validation\ValidationException;
use Tests\Support\IdentityTestCase;

class MonthlyReportLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ac_85_to_93_twelve_lines_control_closure_frozen_alert_and_reasoned_reopening(): void
    {
        FixedCharge::factory()->create(['label' => 'Salaires test', 'monthly_amount' => 100_000, 'is_active' => true]);
        $preparer = $this->roleUser('finance');
        $controller = $this->roleUser('finance');
        $direction = $this->roleUser('direction');
        $service = app(MonthlyReportService::class);
        $report = $service->prepare('2026-07', $preparer);

        $this->assertCount(12, $report->lines);
        $this->assertSame([
            'invoiced', 'received', 'receivables', 'direct_costs', 'salaries', 'fixed_charges',
            'taxes', 'debts', 'cash', 'result', 'reserve', 'covered_months',
        ], array_column($report->lines, 'key'));
        $taxes = collect($report->lines)->firstWhere('key', 'taxes');
        $this->assertSame(0, $taxes['value']);
        $this->assertTrue($taxes['not_applicable']);
        try {
            $service->control($report, $preparer);
            $this->fail('Le préparateur ne contrôle pas son rapport.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('controlled_by', $exception->errors());
        }
        $service->control($report, $controller);
        $service->validate($report, $direction);
        $this->assertSame(MonthlyReportState::Validated, $report->refresh()->state);
        $closure = MonthClosure::query()->where('monthly_report_id', $report->getKey())->sole();
        $this->assertNotNull($closure->frozen_alert_level);
        try {
            app(MonthGuard::class)->assertOpen('2026-07-15');
            $this->fail('Le mois validé doit être fermé.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('juillet 2026', $exception->errors()['received_at'][0]);
        }
        try {
            $service->reopen($report, '', $direction);
            $this->fail('La réouverture sans motif doit échouer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }
        $service->reopen($report, 'Facture tardive documentée.', $direction);
        app(MonthGuard::class)->assertOpen('2026-07-15');
        $this->assertTrue(app(MonthGuard::class)->recordedAfterReopen('2026-07-15'));
        $versionTwo = $service->prepare('2026-07', $preparer);
        $this->assertSame(2, $versionTwo->version);
        $this->assertSame($report->getKey(), $versionTwo->previous_id);
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test rapport story 8.1');

        return $user;
    }
}
