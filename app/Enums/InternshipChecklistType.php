<?php

namespace App\Enums;

enum InternshipChecklistType: string
{
    case Integration = 'integration';
    case Sortie = 'sortie';

    public function label(): string
    {
        return match ($this) {
            self::Integration => "Checklist d'intégration",
            self::Sortie => 'Checklist de sortie',
        };
    }

    /**
     * Libellés générés à l'activation (AC 17) et à la sortie (AC 30), dans l'ordre d'affichage.
     *
     * @return list<string>
     */
    public function items(): array
    {
        return match ($this) {
            self::Integration => [
                'Contrat ou convention signé',
                'Matériel remis',
                'Accès ouverts',
                'Règlement intérieur remis',
                'Première tâche attribuée',
                'Tuteur présenté',
            ],
            self::Sortie => [
                'Livrables remis',
                'Matériel rendu',
                'Accès fermés',
                'Documents sauvegardés',
                'Évaluation finale enregistrée',
            ],
        };
    }
}
