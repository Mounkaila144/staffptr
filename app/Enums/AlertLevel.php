<?php

namespace App\Enums;

enum AlertLevel: string
{
    case Vert = 'vert';
    case Orange = 'orange';
    case Rouge = 'rouge';

    public function label(): string
    {
        return match ($this) {
            self::Vert => 'Vert',
            self::Orange => 'Orange',
            self::Rouge => 'Rouge',
        };
    }

    /**
     * Le niveau rouge bloque l'activation d'un compte employé ou stagiaire, et rien d'autre.
     * Le calcul complet relève de l'Epic 9 ; le point de contrôle est posé ici (AC 18).
     */
    public function blocksAccountActivation(): bool
    {
        return $this === self::Rouge;
    }
}
