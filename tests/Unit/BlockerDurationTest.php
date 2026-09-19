<?php

namespace Tests\Unit;

use App\Models\Accountability\Blocker;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BlockerDurationTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_delays_are_calculated_from_utc_timestamps(): void
    {
        $blocker = new Blocker;
        $blocker->created_at = CarbonImmutable::parse('2026-08-11 10:00:00 UTC');
        $blocker->acknowledged_at = CarbonImmutable::parse('2026-08-11 10:12:00 UTC');
        $blocker->resolved_at = CarbonImmutable::parse('2026-08-11 11:05:00 UTC');

        $this->assertSame(12, $blocker->acknowledgementDelayMinutes());
        $this->assertSame(65, $blocker->resolutionDelayMinutes());
    }
}
