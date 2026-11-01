<?php

namespace App\Services\Identity;

/**
 * Mot de passe temporaire de 8 chiffres (décision du propriétaire, 2026-11-01) : facile à dicter
 * ou à écrire sur papier. Il n'ouvre qu'une session qui impose aussitôt d'en choisir un nouveau,
 * et la connexion reste protégée par le verrouillage après échecs répétés.
 */
final class TemporaryPasswordGenerator
{
    public const LENGTH = 8;

    public function generate(): string
    {
        return str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);
    }
}
