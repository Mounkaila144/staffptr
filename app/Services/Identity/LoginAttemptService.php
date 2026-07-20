<?php

namespace App\Services\Identity;

use App\Enums\LoginAuthenticationStatus;
use App\Enums\UserState;
use App\Models\Identity\LoginAttempt;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

final class LoginAttemptService
{
    private const SYSTEM_ACTOR = "Système d'authentification";

    public function __construct(
        private readonly AttemptedPhoneFingerprint $fingerprint,
        private readonly LoginSecuritySettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function attempt(
        string $phoneAttempted,
        string $password,
        string $ipAddress,
        ?string $userAgent,
    ): LoginAuthenticationResult {
        $phoneFingerprint = $this->fingerprint->for($phoneAttempted);
        $user = $this->findUser($phoneAttempted);
        $now = CarbonImmutable::now('UTC');

        $persistentLock = $this->persistentLock($user, $phoneFingerprint, $now, $ipAddress, $userAgent);

        if ($persistentLock !== null) {
            $this->recordAttempt($user, $phoneFingerprint, false, $ipAddress, $userAgent, $now, $persistentLock);

            return new LoginAuthenticationResult(LoginAuthenticationStatus::Blocked);
        }

        if ($this->isRateLimited($phoneFingerprint, $ipAddress)) {
            $this->recordAttempt($user, $phoneFingerprint, false, $ipAddress, $userAgent, $now);

            return new LoginAuthenticationResult(LoginAuthenticationStatus::Blocked);
        }

        if ($user === null || ! Hash::check($password, $user->password)) {
            $blocked = $this->recordCredentialFailure(
                $user,
                $phoneFingerprint,
                $ipAddress,
                $userAgent,
                $now,
            );
            $this->hitRateLimits($phoneFingerprint, $ipAddress);

            return new LoginAuthenticationResult(
                $blocked ? LoginAuthenticationStatus::Blocked : LoginAuthenticationStatus::InvalidCredentials,
            );
        }

        if ($user->state !== UserState::Actif) {
            $this->recordAttempt($user, $phoneFingerprint, false, $ipAddress, $userAgent, $now);
            $this->hitRateLimits($phoneFingerprint, $ipAddress);

            return new LoginAuthenticationResult(LoginAuthenticationStatus::InactiveAccount);
        }

        DB::transaction(function () use ($user, $phoneFingerprint, $ipAddress, $userAgent, $now): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->failed_attempts !== 0 || $lockedUser->locked_until !== null) {
                $lockedUser->forceFill(['failed_attempts' => 0, 'locked_until' => null])->saveQuietly();
            }

            $this->createAttempt($lockedUser, $phoneFingerprint, true, $ipAddress, $userAgent, $now);
        });
        $this->clearRateLimits($phoneFingerprint, $ipAddress);

        return new LoginAuthenticationResult(LoginAuthenticationStatus::Authenticated, $user);
    }

    public function clearPersistentLockForPasswordReset(
        User $user,
        int $actorId,
        string $actorLabel,
        string $targetLabel,
    ): User {
        $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
        $oldValues = [
            'failed_attempts' => $lockedUser->failed_attempts,
            'locked_until' => $lockedUser->locked_until?->toISOString(),
            'target_label' => $targetLabel,
        ];

        $this->auditLogger->runExplicitly(
            auditable: $lockedUser,
            operation: function () use ($lockedUser): void {
                $lockedUser->forceFill(['failed_attempts' => 0, 'locked_until' => null])->saveOrFail();
            },
            actorId: $actorId,
            actorLabel: $actorLabel,
            action: 'login_lock_cleared_by_password_reset',
            oldValues: $oldValues,
            newValues: ['failed_attempts' => 0, 'locked_until' => null, 'target_label' => $targetLabel],
            reason: "Verrou de {$targetLabel} levé par {$actorLabel} lors d’une réinitialisation vérifiée.",
        );

        return $lockedUser;
    }

    private function findUser(string $phoneAttempted): ?User
    {
        try {
            $phone = PhoneNumber::normalize($phoneAttempted);
        } catch (InvalidArgumentException) {
            return null;
        }

        return User::query()->where('phone', $phone)->first();
    }

    private function persistentLock(
        ?User $user,
        string $phoneFingerprint,
        CarbonImmutable $now,
        string $ipAddress,
        ?string $userAgent,
    ): ?CarbonImmutable {
        if ($user !== null) {
            return $this->knownUserPersistentLock($user, $now, $ipAddress, $userAgent);
        }

        $latestAttempt = LoginAttempt::query()
            ->where('phone_attempted', $phoneFingerprint)
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        if ($latestAttempt?->lock_expires_at === null) {
            return null;
        }

        if ($latestAttempt->lock_expires_at->isAfter($now)) {
            return $latestAttempt->lock_expires_at;
        }

        DB::transaction(function () use ($latestAttempt, $ipAddress, $userAgent): void {
            $this->auditLogger->record(
                actorId: null,
                actorLabel: self::SYSTEM_ACTOR,
                auditable: $latestAttempt,
                action: 'login_lock_expired',
                oldValues: ['lock_expires_at' => $latestAttempt->lock_expires_at->toISOString()],
                newValues: ['lock_expires_at' => null, 'scope' => 'unknown_phone'],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        });

        return null;
    }

    private function knownUserPersistentLock(
        User $user,
        CarbonImmutable $now,
        string $ipAddress,
        ?string $userAgent,
    ): ?CarbonImmutable {
        if ($user->locked_until === null) {
            return null;
        }

        if ($user->locked_until->isAfter($now)) {
            return CarbonImmutable::instance($user->locked_until);
        }

        DB::transaction(function () use ($user, $ipAddress, $userAgent): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->locked_until === null || $lockedUser->locked_until->isFuture()) {
                return;
            }

            $oldValues = [
                'failed_attempts' => $lockedUser->failed_attempts,
                'locked_until' => $lockedUser->locked_until->toISOString(),
            ];

            $this->auditLogger->runExplicitly(
                auditable: $lockedUser,
                operation: function () use ($lockedUser): void {
                    $lockedUser->forceFill(['failed_attempts' => 0, 'locked_until' => null])->saveOrFail();
                },
                actorId: null,
                actorLabel: self::SYSTEM_ACTOR,
                action: 'login_lock_expired',
                oldValues: $oldValues,
                newValues: ['failed_attempts' => 0, 'locked_until' => null],
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        });

        return null;
    }

    private function recordCredentialFailure(
        ?User $user,
        string $phoneFingerprint,
        string $ipAddress,
        ?string $userAgent,
        CarbonImmutable $now,
    ): bool {
        return DB::transaction(function () use ($user, $phoneFingerprint, $ipAddress, $userAgent, $now): bool {
            if ($user !== null) {
                return $this->recordKnownUserFailure(
                    $user,
                    $phoneFingerprint,
                    $ipAddress,
                    $userAgent,
                    $now,
                );
            }

            return $this->recordUnknownPhoneFailure($phoneFingerprint, $ipAddress, $userAgent, $now);
        });
    }

    private function recordKnownUserFailure(
        User $user,
        string $phoneFingerprint,
        string $ipAddress,
        ?string $userAgent,
        CarbonImmutable $now,
    ): bool {
        $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
        $failedAttempts = $lockedUser->failed_attempts + 1;
        $blocked = $failedAttempts >= $this->settings->maxFailedAttempts();
        $lockExpiresAt = $blocked ? $now->addMinutes($this->settings->lockoutMinutes()) : null;
        $attempt = $this->createAttempt(
            $lockedUser,
            $phoneFingerprint,
            false,
            $ipAddress,
            $userAgent,
            $now,
            $lockExpiresAt,
        );

        if (! $blocked) {
            $lockedUser->forceFill(['failed_attempts' => $failedAttempts])->saveQuietly();

            return false;
        }

        $this->auditLogger->runExplicitly(
            auditable: $lockedUser,
            operation: function () use ($lockedUser, $failedAttempts, $lockExpiresAt): void {
                $lockedUser->forceFill([
                    'failed_attempts' => $failedAttempts,
                    'locked_until' => $lockExpiresAt,
                ])->saveOrFail();
            },
            actorId: null,
            actorLabel: self::SYSTEM_ACTOR,
            action: 'login_lock_started',
            oldValues: ['failed_attempts' => $failedAttempts - 1, 'locked_until' => null],
            newValues: [
                'failed_attempts' => $failedAttempts,
                'locked_until' => $lockExpiresAt?->toISOString(),
                'login_attempt_id' => $attempt->getKey(),
            ],
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return true;
    }

    private function recordUnknownPhoneFailure(
        string $phoneFingerprint,
        string $ipAddress,
        ?string $userAgent,
        CarbonImmutable $now,
    ): bool {
        $lastLock = LoginAttempt::query()
            ->where('phone_attempted', $phoneFingerprint)
            ->whereNotNull('lock_expires_at')
            ->latest('occurred_at')
            ->latest('id')
            ->first();
        $failedAttempts = LoginAttempt::query()
            ->whereNull('user_id')
            ->where('phone_attempted', $phoneFingerprint)
            ->where('successful', false)
            ->whereNull('lock_expires_at')
            ->when(
                $lastLock !== null,
                static fn ($query) => $query->where('occurred_at', '>', $lastLock->occurred_at),
            )
            ->count() + 1;
        $blocked = $failedAttempts >= $this->settings->maxFailedAttempts();
        $lockExpiresAt = $blocked ? $now->addMinutes($this->settings->lockoutMinutes()) : null;
        $attempt = $this->createAttempt(
            null,
            $phoneFingerprint,
            false,
            $ipAddress,
            $userAgent,
            $now,
            $lockExpiresAt,
        );

        if (! $blocked) {
            return false;
        }

        $this->auditLogger->record(
            actorId: null,
            actorLabel: self::SYSTEM_ACTOR,
            auditable: $attempt,
            action: 'login_lock_started',
            oldValues: ['failed_attempts' => $failedAttempts - 1, 'locked_until' => null],
            newValues: [
                'failed_attempts' => $failedAttempts,
                'locked_until' => $lockExpiresAt?->toISOString(),
                'scope' => 'unknown_phone',
            ],
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return true;
    }

    private function recordAttempt(
        ?User $user,
        string $phoneFingerprint,
        bool $successful,
        string $ipAddress,
        ?string $userAgent,
        CarbonImmutable $now,
        ?CarbonImmutable $lockExpiresAt = null,
    ): void {
        DB::transaction(function () use (
            $user,
            $phoneFingerprint,
            $successful,
            $ipAddress,
            $userAgent,
            $now,
            $lockExpiresAt,
        ): void {
            $this->createAttempt(
                $user,
                $phoneFingerprint,
                $successful,
                $ipAddress,
                $userAgent,
                $now,
                $lockExpiresAt,
            );
        });
    }

    private function createAttempt(
        ?User $user,
        string $phoneFingerprint,
        bool $successful,
        string $ipAddress,
        ?string $userAgent,
        CarbonImmutable $now,
        ?CarbonImmutable $lockExpiresAt = null,
    ): LoginAttempt {
        return LoginAttempt::query()->create([
            'user_id' => $user?->getKey(),
            'phone_attempted' => $phoneFingerprint,
            'successful' => $successful,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            'occurred_at' => $now,
            'lock_expires_at' => $lockExpiresAt,
        ]);
    }

    private function isRateLimited(string $phoneFingerprint, string $ipAddress): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->phoneRateLimitKey($phoneFingerprint),
            $this->settings->rateLimitAttempts(),
        ) || RateLimiter::tooManyAttempts(
            $this->ipRateLimitKey($ipAddress),
            $this->settings->rateLimitAttempts(),
        );
    }

    private function hitRateLimits(string $phoneFingerprint, string $ipAddress): void
    {
        RateLimiter::hit($this->phoneRateLimitKey($phoneFingerprint), $this->settings->rateLimitDecaySeconds());
        RateLimiter::hit($this->ipRateLimitKey($ipAddress), $this->settings->rateLimitDecaySeconds());
    }

    private function clearRateLimits(string $phoneFingerprint, string $ipAddress): void
    {
        RateLimiter::clear($this->phoneRateLimitKey($phoneFingerprint));
        RateLimiter::clear($this->ipRateLimitKey($ipAddress));
    }

    public function phoneRateLimitKey(string $phoneFingerprint): string
    {
        return "login:phone:{$phoneFingerprint}";
    }

    public function ipRateLimitKey(string $ipAddress): string
    {
        return 'login:ip:'.$this->fingerprint->for($ipAddress);
    }
}
