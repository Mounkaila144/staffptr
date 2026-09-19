<?php

namespace App\Enums;

enum DocumentType: string
{
    case Contrat = 'contrat';
    case Convention = 'convention';
    case FichePoste = 'fiche_poste';
    case EngagementSigne = 'engagement_signe';

    public function label(): string
    {
        return match ($this) {
            self::Contrat => 'Contrat',
            self::Convention => 'Convention',
            self::FichePoste => 'Fiche de poste',
            self::EngagementSigne => 'Engagement signé',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
