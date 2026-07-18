<?php

namespace Tests\Unit;

use App\Support\DateTimeFormatter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class DateTimeFormatterTest extends TestCase
{
    public function test_it_formats_utc_timestamps_in_niamey_across_midnight(): void
    {
        $utcTimestamp = new DateTimeImmutable('2026-07-18 23:30:00', new DateTimeZone('UTC'));

        $this->assertSame(
            '19/07/2026 00:30',
            DateTimeFormatter::format($utcTimestamp, timezone: 'Africa/Niamey'),
        );
        $this->assertSame('2026-07-18 23:30', $utcTimestamp->format('Y-m-d H:i'));
    }
}
