<?php

namespace App\Services\Platform\Invariants;

final readonly class InvariantResult
{
    private function __construct(
        public string $name,
        public bool $passed,
        public string $observed,
        public string $expected,
        /**
         * Contrôle **en attente d'une dépendance non livrée**, à distinguer d'un contrôle réussi
         * comme d'un contrôle en écart.
         *
         * Le besoin vient de l'AC 20 de la story 10.1 : la fraîcheur de sauvegarde fait partie des
         * invariants, mais son contrat appartient à la story 11.1. Le faire passer serait mentir ;
         * le faire échouer rendrait la commande rouge en permanence et l'on cesserait de la
         * regarder. Un troisième état garde la dépendance visible sans l'un ni l'autre.
         */
        public bool $pending = false,
    ) {}

    public static function pass(string $name, string $observed, string $expected): self
    {
        return new self($name, true, $observed, $expected);
    }

    public static function fail(string $name, string $observed, string $expected): self
    {
        return new self($name, false, $observed, $expected);
    }

    public static function pending(string $name, string $observed, string $expected): self
    {
        return new self($name, false, $observed, $expected, pending: true);
    }
}
