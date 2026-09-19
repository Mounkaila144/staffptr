<?php

namespace App\Support;

use InvalidArgumentException;

final class PunctualityCalculator
{
    public function percentage(int $onTimeReports, int $expectedReports): float
    {
        if ($expectedReports < 0 || $onTimeReports < 0 || $onTimeReports > $expectedReports) {
            throw new InvalidArgumentException('Les volumes de ponctualité sont incohérents.');
        }

        return $expectedReports === 0 ? 0.0 : round(($onTimeReports / $expectedReports) * 100, 1);
    }
}
