<?php

namespace App\Enums;

enum ObjectiveState: string
{
    case Brouillon = 'brouillon';
    case Valide = 'valide';
    case EnCours = 'en_cours';
    case Atteint = 'atteint';
    case PartiellementAtteint = 'partiellement_atteint';
    case NonAtteint = 'non_atteint';
    case Bloque = 'bloque';
    case Annule = 'annule';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Brouillon => [self::Valide, self::Annule],
            self::Valide => [self::EnCours, self::Bloque, self::Annule],
            self::EnCours => [self::Atteint, self::PartiellementAtteint, self::NonAtteint, self::Bloque, self::Annule],
            self::Bloque => [self::EnCours, self::PartiellementAtteint, self::NonAtteint, self::Annule],
            self::Atteint, self::PartiellementAtteint, self::NonAtteint, self::Annule => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function countsTowardMonthlyLimit(): bool
    {
        return ! in_array($this, [self::Brouillon, self::Annule], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon', self::Valide => 'Validé', self::EnCours => 'En cours',
            self::Atteint => 'Atteint', self::PartiellementAtteint => 'Partiellement atteint',
            self::NonAtteint => 'Non atteint', self::Bloque => 'Bloqué', self::Annule => 'Annulé',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Atteint => 'vert', self::PartiellementAtteint, self::EnCours => 'orange',
            self::NonAtteint, self::Bloque => 'rouge', default => 'gris',
        };
    }
}
