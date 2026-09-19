<?php

namespace App\Enums;

enum InternshipState: string
{
    case Actif = 'actif';
    case Termine = 'termine';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Termine => 'Terminé',
            self::Archive => 'Archivé',
        };
    }

    /**
     * Seuls les stages actifs occupent une place chez le tuteur (AC 23).
     */
    public function occupiesTutorSlot(): bool
    {
        return $this === self::Actif;
    }

    /** @return list<string> */
    public static function occupyingValues(): array
    {
        return array_values(array_map(
            static fn (self $state): string => $state->value,
            array_filter(self::cases(), static fn (self $state): bool => $state->occupiesTutorSlot()),
        ));
    }
}
