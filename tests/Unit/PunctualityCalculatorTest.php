<?php

namespace Tests\Unit;

use App\Support\PunctualityCalculator;
use PHPUnit\Framework\TestCase;

class PunctualityCalculatorTest extends TestCase
{
    public function test_percentage_uses_expected_reports_as_denominator(): void
    {
        $calculator = new PunctualityCalculator;

        $this->assertSame(75.0, $calculator->percentage(3, 4));
        $this->assertSame(0.0, $calculator->percentage(0, 0));
    }
}
