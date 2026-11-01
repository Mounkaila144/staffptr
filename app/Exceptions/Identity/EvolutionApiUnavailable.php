<?php

namespace App\Exceptions\Identity;

use Exception;

class EvolutionApiUnavailable extends Exception
{
    public function __construct()
    {
        parent::__construct("Le code WhatsApp n'a pas pu être envoyé. Un compte direction peut réinitialiser sans code avec « WhatsApp indisponible ? Réinitialiser sans code ».");
    }
}
