<?php

namespace Tests\Feature;

use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;
use App\Services\Finance\FinancialReportReminderService;
use App\Services\Identity\RoleAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IdentityTestCase;

class FinancialReportReminderTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        // L'écriture `database` reste réelle — c'est elle qui fait foi — et la mise en file
        // WhatsApp est interceptée : aucun appel réseau ne part d'un test.
        Queue::fake();
    }

    public function test_ac_89_notifies_before_and_after_the_fifth_without_duplicates(): void
    {
        $finance = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($finance, 'finance', null, 'Test rappel financier');
        $service = app(FinancialReportReminderService::class);
        $this->assertSame(1, $service->dispatchDue(CarbonImmutable::parse('2026-08-04', 'Africa/Niamey')));
        $this->assertSame(0, $service->dispatchDue(CarbonImmutable::parse('2026-08-04', 'Africa/Niamey')));
        $this->assertSame(1, $service->dispatchDue(CarbonImmutable::parse('2026-08-05', 'Africa/Niamey')));
        $this->assertDatabaseCount('notifications', 2);

        MonthlyReport::factory()->create(['month' => '2026-07-01']);
        $this->assertSame(0, $service->dispatchDue(CarbonImmutable::parse('2026-08-06', 'Africa/Niamey')));
    }
}
