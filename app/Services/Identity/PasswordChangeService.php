<?php

namespace App\Services\Identity;

use App\Models\Identity\User;
use Illuminate\Contracts\Session\Session;

class PasswordChangeService
{
    public function __construct(private readonly IdentityService $identityService) {}

    public function change(User $user, string $password, Session $session): User
    {
        $actorLabel = $user->person()->value('full_name');

        $updatedUser = $this->identityService->changePassword(
            user: $user,
            password: $password,
            actorId: (int) $user->getKey(),
            actorLabel: is_string($actorLabel) ? $actorLabel : "Compte {$user->getKey()}",
            reason: 'Changement de mot de passe imposé à la première connexion',
        );

        // Toutes les anciennes lignes, y compris celle de cette requête, ont été supprimées dans
        // la transaction métier. Un nouvel identifiant conserve alors uniquement la session active.
        $session->regenerate(destroy: true);

        return $updatedUser;
    }
}
