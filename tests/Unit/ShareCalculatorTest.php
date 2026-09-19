<?php

namespace Tests\Unit;

use App\Enums\ShareType;
use App\Support\ShareCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ShareCalculatorTest extends TestCase
{
    public function test_ac_15_without_contributor_ptr_niger_receives_one_hundred_percent(): void
    {
        $result = $this->calculator()->calculate(1_000_000, null, true, [10, 11]);

        $this->assertCount(1, $result['shares']);
        $this->assertSame(ShareType::PtrNiger->value, $result['shares'][0]['share_type']);
        $this->assertSame(1_000_000, $result['shares'][0]['share_amount']);
    }

    public function test_ac_15_contributor_without_execution_receives_ten_percent_and_ptr_ninety(): void
    {
        $result = $this->calculator()->calculate(1_000_000, 7, false);

        $this->assertSame([100_000, 900_000], array_column($result['shares'], 'share_amount'));
        $this->assertSame(['10 %', '90 %'], array_column($result['shares'], 'rate_label'));
        $this->assertSame(1_000_000, array_sum(array_column($result['shares'], 'share_amount')));
    }

    public function test_ac_15_contributor_with_execution_uses_ten_sixty_thirty(): void
    {
        $result = $this->calculator()->calculate(1_000_000, 7, true, [20]);

        $this->assertSame([100_000, 600_000, 300_000], array_column($result['shares'], 'share_amount'));
        $this->assertSame([
            ShareType::Contributor->value,
            ShareType::PtrNiger->value,
            ShareType::Executor->value,
        ], array_column($result['shares'], 'share_type'));
    }

    public function test_ac_16_two_and_three_executors_receive_strictly_equal_shares_when_divisible(): void
    {
        $two = $this->calculator()->calculate(1_000_000, 7, true, [20, 21]);
        $three = $this->calculator()->calculate(1_000_000, 7, true, [20, 21, 22]);

        $this->assertSame([150_000, 150_000], array_slice(array_column($two['shares'], 'share_amount'), 2));
        $this->assertSame([100_000, 100_000, 100_000], array_slice(array_column($three['shares'], 'share_amount'), 2));
    }

    public function test_ac_16_integer_remainder_is_deterministically_assigned_to_rank_one(): void
    {
        $result = $this->calculator()->calculate(10, 7, true, [20, 21]);

        $this->assertSame([1, 6, 2, 1], array_column($result['shares'], 'share_amount'));
        $this->assertSame(10, array_sum(array_column($result['shares'], 'share_amount')));
        $this->assertStringContainsString('reliquat', $result['method']);
    }

    public function test_ac_16_sum_is_exact_for_many_integer_bases(): void
    {
        foreach ([0, 1, 2, 7, 11, 99, 101, 999, 1_000_003, 9_999_999] as $base) {
            $result = $this->calculator()->calculate($base, 7, true, [20, 21, 22]);
            $this->assertSame($base, array_sum(array_column($result['shares'], 'share_amount')));
        }
    }

    public function test_contract_with_execution_requires_at_least_one_executor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator()->calculate(100_000, 7, true, []);
    }

    private function calculator(): ShareCalculator
    {
        return new ShareCalculator;
    }
}
