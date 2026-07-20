<?php

namespace App\Services\Identity;

use App\Models\Identity\User;

class PasswordChangeService
{
    public function __construct(private readonly IdentityService $identityService) {}

    public function change(User $user, string $password): User
    {
        $actorLabel = $user->person()->value('full_name');

        return $this->identityService->changePassword(
            user: $user,
            password: $password,
            actorId: (int) $user->getKey(),
            actorLabel: is_string($actorLabel) ? $actorLabel : "Compte {$user->getKey()}",
            reason: 'Changement de mot de passe imposé à la première connexion',
        );
    }
}
