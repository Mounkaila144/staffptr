<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Prevu = 'prevu';
    case Actif = 'actif';
    case Bloque = 'bloque';
    case EnValidation = 'en_validation';
    case Livre = 'livre';
    case Cloture = 'cloture';
    case Annule = 'annule';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Prevu => [self::Actif, self::Bloque, self::Annule],
            self::Actif => [self::Bloque, self::EnValidation, self::Annule],
            self::Bloque => [self::Actif, self::Annule],
            self::EnValidation => [self::Actif, self::Bloque, self::Livre],
            self::Livre => [self::Cloture],
            self::Cloture, self::Annule => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Prevu => 'Prévu', self::Actif => 'Actif', self::Bloque => 'Bloqué',
            self::EnValidation => 'En validation', self::Livre => 'Livré',
            self::Cloture => 'Clôturé', self::Annule => 'Annulé',
        };
    }
}
