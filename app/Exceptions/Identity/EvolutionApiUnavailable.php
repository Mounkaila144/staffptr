<?php

namespace App\Exceptions\Identity;

use Exception;

class EvolutionApiUnavailable extends Exception
{
    public function __construct()
    {
        parent::__construct("Le code WhatsApp n'a pas pu être envoyé. La réinitialisation reste bloquée jusqu'au rétablissement du service.");
    }
}
