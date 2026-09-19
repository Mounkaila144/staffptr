<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Services\Identity\ContractEndingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\IdentityTestCase;

class ContractEndingServiceTest extends IdentityTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_ac_4_service_lists_inclusive_upcoming_contract_boundaries_without_notification(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00 Africa/Niamey');
        Notification::fake();
        $today = User::factory()->active()->endingInDays(0)->create();
        $boundary = User::factory()->active()->endingInDays(30)->create();
        User::factory()->active()->endingInDays(31)->create();
        User::factory()->active()->create(['contract_end_date' => '2026-07-19']);
        User::factory()->suspended()->endingInDays(10)->create();

        $ending = app(ContractEndingService::class)->endingWithin(30);

        $this->assertSame([$today->getKey(), $boundary->getKey()], $ending->modelKeys());
        Notification::assertNothingSent();
    }

    public function test_ac_4_default_delay_is_read_from_settings(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00 Africa/Niamey');
        $this->setSetting('contract_end_warning_days', 7);
        $included = User::factory()->active()->endingInDays(7)->create();
        User::factory()->active()->endingInDays(8)->create();

        $ending = app(ContractEndingService::class)->endingWithin();

        $this->assertSame([$included->getKey()], $ending->modelKeys());
    }
}
