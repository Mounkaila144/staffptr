<?php

namespace App\Services\Platform\Invariants;

use App\Enums\ContributionEntryType;
use App\Enums\ContributionOrigin;
use App\Enums\ShareType;
use App\Models\Finance\ContributionShare;
use App\Models\Finance\ShareEntitlement;
use App\Support\VintageCoefficient;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Cohérence du registre des parts de contribution (story 12.1, AC 23).
 *
 * Deux vérifications, toutes deux sur des entiers :
 *
 * 1. L'assiette nette du registre pour l'origine « encaissement » égale la somme des parts
 *    `ptr_niger` non extournées des encaissements dont le contrat désigne un apporteur directeur.
 *    Le montant émis est par construction celui de la part entreprise (AC 1) ; c'est donc sur cette
 *    assiette que l'égalité se constate, le coefficient de millésime venant ensuite.
 * 2. Chaque ligne porte bien `assiette × coefficient` en division entière, avec le coefficient
 *    figé sur la ligne : un barème modifié après coup ne doit rien avoir réécrit.
 */
final class ContributionShareIntegrityInvariant implements InvariantCheck
{
    private const NAME = 'Cohérence du registre des parts de contribution';

    private const EXPECTED = 'assiette du registre égale aux parts PTR Niger des encaissements à apporteur directeur';

    public function __construct(private readonly VintageCoefficient $vintage) {}

    public function check(): InvariantResult
    {
        try {
            $expected = (int) ShareEntitlement::query()
                ->where('share_type', ShareType::PtrNiger->value)
                ->whereNull('reversed_at')
                ->whereHas('contract.contributor', fn (Builder $contributor): Builder => $contributor
                    ->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', 'direction')))
                ->sum('share_amount');
            $entries = ContributionShare::query()
                ->ofOrigin(ContributionOrigin::Encaissement)
                ->get();
            $observed = (int) $entries->sum(fn (ContributionShare $entry): int => $entry->signedBaseAmount());
            $misvalued = ContributionShare::query()->get()
                ->filter(fn (ContributionShare $entry): bool => $entry->issued_shares !== $this->vintage->shares(
                    $entry->base_amount,
                    $entry->coefficient_basis_points,
                ))
                ->pluck('id')
                ->all();
        } catch (Throwable) {
            return InvariantResult::fail(self::NAME, 'registre illisible', self::EXPECTED);
        }

        $issues = [];

        if ($observed !== $expected) {
            $issues[] = "assiette {$observed} contre {$expected} attendus";
        }

        if ($misvalued !== []) {
            $issues[] = 'coefficient non respecté sur #'.implode(', #', $misvalued);
        }

        if ($issues !== []) {
            return InvariantResult::fail(self::NAME, implode(' ; ', $issues), self::EXPECTED);
        }

        $emissions = ContributionShare::query()->where('entry_type', ContributionEntryType::Emission->value)->count();

        return InvariantResult::pass(
            self::NAME,
            "{$emissions} émission(s) pour une assiette de {$observed}",
            self::EXPECTED,
        );
    }
}
