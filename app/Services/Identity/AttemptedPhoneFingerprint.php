<?php

namespace App\Services\Identity;

use App\Support\PhoneNumber;
use InvalidArgumentException;
use RuntimeException;

final class AttemptedPhoneFingerprint
{
    public function for(string $phoneAttempted): string
    {
        try {
            $canonicalValue = PhoneNumber::normalize($phoneAttempted);
        } catch (InvalidArgumentException) {
            $canonicalValue = mb_strtolower(trim($phoneAttempted));
        }

        return hash_hmac('sha256', $canonicalValue, $this->key());
    }

    private function key(): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException("APP_KEY est requis pour protéger l'empreinte du numéro tenté.");
        }

        if (! str_starts_with($key, 'base64:')) {
            return $key;
        }

        $decoded = base64_decode(substr($key, 7), true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException("APP_KEY base64 n'est pas valide.");
        }

        return $decoded;
    }
}
