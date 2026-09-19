<?php

namespace App\Services\Identity;

use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Exceptions\Identity\PasswordResetVerificationFailed;
use App\Models\Identity\User;
use App\Services\Platform\EvolutionApiClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PasswordResetVerificationService
{
    public const EXPIRATION_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly EvolutionApiClient $evolutionApiClient) {}

    /** @throws EvolutionApiUnavailable */
    public function initiate(User $actor, User $target): void
    {
        $confirmationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = $this->cacheKey($actor, $target);
        $payload = [
            'digest' => $this->digest($confirmationCode),
            'attempts' => 0,
            'expires_at' => CarbonImmutable::now('UTC')->addMinutes(self::EXPIRATION_MINUTES)->toISOString(),
        ];

        if (! Cache::put($cacheKey, $payload, now()->addMinutes(self::EXPIRATION_MINUTES))) {
            throw new EvolutionApiUnavailable;
        }

        try {
            $this->evolutionApiClient->sendPasswordResetConfirmation($target->phone, $confirmationCode);
        } catch (EvolutionApiUnavailable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    /** @throws PasswordResetVerificationFailed */
    public function consume(User $actor, User $target, string $confirmationCode): void
    {
        try {
            Cache::lock($this->lockKey($actor, $target), 5)->block(3, function () use ($actor, $target, $confirmationCode): void {
                $cacheKey = $this->cacheKey($actor, $target);
                $challenge = Cache::get($cacheKey);

                if (! is_array($challenge) || ! isset($challenge['digest'], $challenge['attempts'], $challenge['expires_at'])) {
                    throw new PasswordResetVerificationFailed;
                }

                $expiresAt = CarbonImmutable::parse((string) $challenge['expires_at']);

                if ($expiresAt->isPast()) {
                    Cache::forget($cacheKey);

                    throw new PasswordResetVerificationFailed;
                }

                if (! hash_equals((string) $challenge['digest'], $this->digest($confirmationCode))) {
                    $attempts = (int) $challenge['attempts'] + 1;

                    if ($attempts >= self::MAX_ATTEMPTS) {
                        Cache::forget($cacheKey);

                        throw new PasswordResetVerificationFailed('Trop de codes invalides. Relancez la procédure pour recevoir un nouveau code.');
                    }

                    $challenge['attempts'] = $attempts;
                    Cache::put($cacheKey, $challenge, $expiresAt);

                    throw new PasswordResetVerificationFailed;
                }

                Cache::forget($cacheKey);
            });
        } catch (LockTimeoutException) {
            throw new PasswordResetVerificationFailed('Une vérification est déjà en cours. Réessayez dans quelques secondes.');
        }
    }

    private function digest(string $confirmationCode): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('APP_KEY doit être configurée pour protéger les codes de confirmation.');
        }

        return hash_hmac('sha256', $confirmationCode, $key);
    }

    private function cacheKey(User $actor, User $target): string
    {
        return "password-reset:{$actor->getKey()}:{$target->getKey()}";
    }

    private function lockKey(User $actor, User $target): string
    {
        return $this->cacheKey($actor, $target).':lock';
    }
}
