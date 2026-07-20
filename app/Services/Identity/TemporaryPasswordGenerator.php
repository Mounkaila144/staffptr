<?php

namespace App\Services\Identity;

final class TemporaryPasswordGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
