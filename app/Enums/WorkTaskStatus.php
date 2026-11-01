<?php

namespace App\Enums;

enum WorkTaskStatus: string
{
    case AFaire = 'a_faire';
    case EnCours = 'en_cours';
    case Bloquee = 'bloquee';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    /**
     * Une tâche ouverte peut aller partout ; une tâche close se rouvre sans repasser par la
     * création, pour qu'une erreur de clic ne laisse jamais une tâche figée.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AFaire => [self::EnCours, self::Bloquee, self::Terminee, self::Annulee],
            self::EnCours => [self::AFaire, self::Bloquee, self::Terminee, self::Annulee],
            self::Bloquee => [self::AFaire, self::EnCours, self::Terminee, self::Annulee],
            self::Terminee => [self::EnCours],
            self::Annulee => [self::AFaire],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::AFaire => 'À faire', self::EnCours => 'En cours', self::Bloquee => 'Bloquée',
            self::Terminee => 'Terminée', self::Annulee => 'Annulée',
        };
    }
}
