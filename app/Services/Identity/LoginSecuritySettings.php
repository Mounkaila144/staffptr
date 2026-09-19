<?php

namespace App\Services\Identity;

use App\Services\Platform\SettingsService;
use InvalidArgumentException;

final class LoginSecuritySettings
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function maxFailedAttempts(): int
    {
        return $this->settingsService->loginMaxFailedAttempts();
    }

    public function lockoutMinutes(): int
    {
        return $this->settingsService->loginLockoutMinutes();
    }

    public function rateLimitAttempts(): int
    {
        return $this->positiveConfigInteger('rate_limit_attempts');
    }

    public function rateLimitDecaySeconds(): int
    {
        return $this->positiveConfigInteger('rate_limit_decay_seconds');
    }

    public function blockedMessage(): string
    {
        return sprintf(
            'Trop de tentatives. Réessayez dans %d minutes, ou contactez la direction.',
            $this->lockoutMinutes(),
        );
    }

    private function positiveConfigInteger(string $key): int
    {
        $value = config("login-security.{$key}");

        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("login-security.{$key} doit être un entier positif.");
        }

        return $value;
    }
}
