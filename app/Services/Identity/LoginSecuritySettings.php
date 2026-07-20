<?php

namespace App\Services\Identity;

use InvalidArgumentException;

final class LoginSecuritySettings
{
    public function maxFailedAttempts(): int
    {
        return $this->positiveInteger('max_failed_attempts');
    }

    public function lockoutMinutes(): int
    {
        return $this->positiveInteger('lockout_minutes');
    }

    public function rateLimitAttempts(): int
    {
        return $this->positiveInteger('rate_limit_attempts');
    }

    public function rateLimitDecaySeconds(): int
    {
        return $this->positiveInteger('rate_limit_decay_seconds');
    }

    public function blockedMessage(): string
    {
        return sprintf(
            'Trop de tentatives. Réessayez dans %d minutes, ou contactez la direction.',
            $this->lockoutMinutes(),
        );
    }

    private function positiveInteger(string $key): int
    {
        $value = config("login-security.{$key}");

        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("login-security.{$key} doit être un entier positif.");
        }

        return $value;
    }
}
