<?php

namespace App\Enums;

enum DeliverableStatus: string
{
    case Prevu = 'prevu';
    case Soumis = 'soumis';
    case CorrectionDemandee = 'correction_demandee';
    case Valide = 'valide';
    case Annule = 'annule';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Prevu => [self::Soumis, self::Annule],
            self::Soumis => [self::CorrectionDemandee, self::Valide, self::Annule],
            self::CorrectionDemandee => [self::Soumis, self::Annule],
            self::Valide, self::Annule => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Prevu => 'Prévu', self::Soumis => 'Soumis',
            self::CorrectionDemandee => 'Correction demandée', self::Valide => 'Validé',
            self::Annule => 'Annulé',
        };
    }
}
