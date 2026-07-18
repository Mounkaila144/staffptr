<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

final class DateTimeFormatter
{
    public static function format(
        DateTimeInterface $dateTime,
        string $format = 'd/m/Y H:i',
        ?string $timezone = null,
    ): string {
        $displayTimezone = new DateTimeZone($timezone ?? (string) config('app.display_timezone'));

        return CarbonImmutable::instance($dateTime)
            ->setTimezone($displayTimezone)
            ->format($format);
    }
}
