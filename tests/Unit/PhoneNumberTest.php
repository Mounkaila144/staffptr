<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_it_normalizes_equivalent_nigerien_numbers(): void
    {
        foreach (['90123456', '+22790123456', '00227 90 12 34 56'] as $input) {
            $this->assertSame('+22790123456', PhoneNumber::normalize($input));
        }
    }

    public function test_it_removes_supported_separators(): void
    {
        foreach (['90.12.34.56', '90-12-34-56', '(90) 12 34 56'] as $input) {
            $this->assertSame('+22790123456', PhoneNumber::normalize($input));
        }
    }

    public function test_it_accepts_fixed_line_prefixes(): void
    {
        $this->assertSame('+22720123456', PhoneNumber::normalize('20 12 34 56'));
    }

    public function test_it_rejects_invalid_numbers_with_the_required_message(): void
    {
        foreach (['', '9012345', '901234567', '+22890123456', '90AB3456'] as $input) {
            try {
                PhoneNumber::normalize($input);
                $this->fail('Le numéro invalide aurait dû être refusé.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(PhoneNumber::INVALID_MESSAGE, $exception->getMessage());
            }
        }
    }
}
