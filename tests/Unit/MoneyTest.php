<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_formats_xof_amounts_for_display(): void
    {
        $this->assertSame('0 FCFA', Money::from(0)->format());
        $this->assertSame('999 FCFA', Money::from(999)->format());
        $this->assertSame("1\u{202F}250\u{202F}000 FCFA", Money::from(1_250_000)->format());
        $this->assertSame("12\u{202F}345\u{202F}678\u{202F}901 FCFA", Money::from(12_345_678_901)->format());
    }

    public function test_its_raw_value_is_an_integer_for_persistence_and_exports(): void
    {
        $value = Money::from(1_250_000)->value();

        $this->assertIsInt($value);
        $this->assertSame(1_250_000, $value);
        $this->assertNotSame(Money::from($value)->format(), $value);
    }

    public function test_it_rejects_floating_point_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant doit être un entier XOF positif ou nul.');

        Money::from(1_250.00);
    }

    public function test_it_rejects_numeric_strings_instead_of_coercing_them(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::from('1250');
    }

    public function test_it_rejects_negative_amounts_for_unsigned_storage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::from(-1);
    }

    public function test_it_allocates_basis_points_and_assigns_the_integer_remainder_to_rank_one(): void
    {
        $allocation = Money::from(11)->allocateByBasisPoints([
            'rank_one' => 1_000,
            'rank_two' => 6_000,
            'rank_three' => 3_000,
        ]);

        $this->assertSame(['rank_one' => 2, 'rank_two' => 6, 'rank_three' => 3], $allocation);
        $this->assertSame(11, array_sum($allocation));
    }

    public function test_it_splits_equally_and_assigns_the_integer_remainder_to_the_first_beneficiary(): void
    {
        $this->assertSame([4, 3, 3], Money::from(10)->splitEqually(3));
        $this->assertSame([0, 0], Money::from(0)->splitEqually(2));
    }

    public function test_it_rejects_an_invalid_rate_total_or_empty_split(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::from(100)->allocateByBasisPoints(['invalid' => 9_999]);
    }
}
