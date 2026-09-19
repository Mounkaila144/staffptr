<?php

namespace App\Exceptions\Identity;

use DomainException;

final class RoleAssignmentConflict extends DomainException
{
    public static function superAdminBusinessConflict(): self
    {
        return new self('Un compte super_admin ne peut porter aucun rôle ni permission métier.');
    }

    public static function directionLimitReached(): self
    {
        return new self(
            'Deux comptes direction existent déjà. Retirez le rôle direction d’un compte avant d’en attribuer un autre.',
        );
    }
}
