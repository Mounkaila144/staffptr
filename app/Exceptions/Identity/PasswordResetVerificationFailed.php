<?php

namespace App\Exceptions\Identity;

use Exception;

class PasswordResetVerificationFailed extends Exception
{
    public function __construct(string $message = 'Le code de confirmation est invalide ou a expiré. Relancez la procédure si nécessaire.')
    {
        parent::__construct($message);
    }
}
