<?php

namespace App\Services\Finance;

use App\Enums\AlertLevel;
use App\Models\Finance\Expense;

/**
 * Avertissement — **et rien de plus** — affiché à l'approbation d'une dépense non essentielle en
 * niveau d'alerte rouge (AC 9, C9, FR164, architecture § 13.5).
 *
 * Le nom de la classe est délibérément « notice » et non « guard » : elle ne refuse jamais rien.
 * L'approbation reste possible ; c'est à la direction de décider en connaissance de cause. Le
 * rouge ne bloque qu'une seule écriture dans tout le produit, l'activation d'un nouveau compte
 * (AC 8), et ne bloque jamais une personne (AC 12).
 *
 * Elle ne concerne pas non plus les parts de 10 % et 30 %, dont le calcul et le versement restent
 * entiers en rouge (AC 10, RM-14, CONTRA-07).
 */
final readonly class AlertLevelExpenseNotice
{
    public function __construct(private AlertLevelService $alertLevelService) {}

    /**
     * Texte à afficher, ou `null` s'il n'y a rien à signaler. La dépense reste approuvable dans
     * tous les cas.
     */
    public function forExpense(Expense $expense): ?string
    {
        if (! $this->applies($expense)) {
            return null;
        }

        return sprintf(
            "Niveau d'alerte %s : cette dépense relève d'une catégorie non essentielle. L'approbation reste possible, mais elle pèse sur une trésorerie déjà sous l'assiette des charges fixes.",
            AlertLevel::Rouge->label(),
        );
    }

    /**
     * La dépense tombe-t-elle sous l'avertissement ? Une catégorie marquée « essentielle » n'en
     * déclenche aucun, quel que soit le niveau.
     */
    public function applies(Expense $expense): bool
    {
        if ($this->alertLevelService->current() !== AlertLevel::Rouge) {
            return false;
        }

        $category = $expense->relationLoaded('category') ? $expense->category : $expense->category()->first();

        return $category !== null && ! $category->is_essential;
    }
}
