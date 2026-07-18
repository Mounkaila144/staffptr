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
}
