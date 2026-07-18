<?php

namespace App\Support;

use InvalidArgumentException;

final class PhoneNumber
{
    public const INVALID_MESSAGE = "Ce numéro n'est pas valide. Saisissez 8 chiffres, ou le numéro complet avec son indicatif.";

    public static function normalize(string $phoneNumber): string
    {
        $normalized = preg_replace('/[\s.\-()]+/u', '', trim($phoneNumber));

        if ($normalized === null || $normalized === '') {
            throw new InvalidArgumentException(self::INVALID_MESSAGE);
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        if (! str_starts_with($normalized, '+')) {
            $normalized = '+227'.$normalized;
        }

        if (preg_match('/^\+227[0-9]{8}$/', $normalized) !== 1) {
            throw new InvalidArgumentException(self::INVALID_MESSAGE);
        }

        return $normalized;
    }
}
