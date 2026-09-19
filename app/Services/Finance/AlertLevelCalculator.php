<?php

namespace App\Services\Finance;

use App\Enums\AlertLevel;
use InvalidArgumentException;

/**
 * Calcul pur du niveau d'alerte financière (FR161 à FR164, AC 1 à 2).
 *
 * Aucune lecture de base, aucun cache, aucune date : cette classe ne connaît que trois entiers
 * XOF. Toute la charge de trouver l'assiette et les encaissements appartient à
 * {@see AlertLevelService}. C'est ce qui permet de couvrir les trois niveaux par des tests Unit.
 *
 * L'assiette est la somme des charges fixes **actives du paramétrage** : aucune liste codée en dur
 * n'existe ici ni ailleurs (AC 1, AC 39).
 */
final class AlertLevelCalculator
{
    /**
     * Niveau du mois observé.
     *
     * - **Vert** : les encaissements du mois atteignent l'assiette.
     * - **Orange** : un seul mois sous l'assiette.
     * - **Rouge** : deux mois consécutifs sous l'assiette.
     *
     * @param  int  $baseline  Assiette du mois, en francs CFA entiers.
     * @param  int  $collections  Encaissements validés du mois observé.
     * @param  int  $previousCollections  Encaissements validés du mois précédent.
     */
    public function level(int $baseline, int $collections, int $previousCollections): AlertLevel
    {
        $this->assertPositive($baseline, 'assiette');
        $this->assertPositive($collections, 'encaissements du mois');
        $this->assertPositive($previousCollections, 'encaissements du mois précédent');

        if ($collections >= $baseline) {
            return AlertLevel::Vert;
        }

        if ($previousCollections < $baseline) {
            return AlertLevel::Rouge;
        }

        return AlertLevel::Orange;
    }

    /**
     * Méthode de calcul rendue lisible à l'écran (AC 6). Elle nomme l'assiette, les encaissements
     * comparés et la règle appliquée, pour qu'aucun niveau affiché ne soit une boîte noire.
     */
    public function method(int $baseline, int $collections, int $previousCollections): string
    {
        return sprintf(
            "Assiette = somme des charges fixes actives du paramétrage (%s F CFA). Encaissements validés du mois : %s F CFA ; du mois précédent : %s F CFA. Vert si les encaissements du mois atteignent l'assiette, orange après un mois en dessous, rouge après deux mois consécutifs en dessous.",
            number_format($baseline, 0, ',', ' '),
            number_format($collections, 0, ',', ' '),
            number_format($previousCollections, 0, ',', ' '),
        );
    }

    private function assertPositive(int $amount, string $label): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException("Le montant « {$label} » ne peut pas être négatif.");
        }
    }
}
