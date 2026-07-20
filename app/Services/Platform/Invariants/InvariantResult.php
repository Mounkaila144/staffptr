<?php

namespace App\Services\Platform\Invariants;

final readonly class InvariantResult
{
    public function __construct(
        public string $name,
        public bool $passed,
        public string $observed,
        public string $expected,
    ) {}

    public static function pass(string $name, string $observed, string $expected): self
    {
        return new self($name, true, $observed, $expected);
    }

    public static function fail(string $name, string $observed, string $expected): self
    {
        return new self($name, false, $observed, $expected);
    }
}
