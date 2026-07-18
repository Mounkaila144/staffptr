<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class Money
{
    private int $amount;

    public function __construct(mixed $amount)
    {
        if (! is_int($amount) || $amount < 0) {
            throw new InvalidArgumentException('Le montant doit être un entier XOF positif ou nul.');
        }

        $this->amount = $amount;
    }

    public static function from(mixed $amount): self
    {
        return new self($amount);
    }

    public function value(): int
    {
        return $this->amount;
    }

    public function format(): string
    {
        return number_format($this->amount, 0, '', "\u{202F}").' FCFA';
    }
}
