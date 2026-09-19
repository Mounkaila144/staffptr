<?php

namespace App\Services\Identity;

use App\Enums\InternshipIntakeState;
use App\Enums\ObjectiveState;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Services\Finance\AlertLevelService;

/**
 * Trois conditions séparées gouvernent le passage d'un compte `stagiaire` à `actif` :
 * fiche d'entrée approuvée, tuteur désigné et trois objectifs enregistrés (AC 16, 41, FR84, CA-03).
 *
 * La classe vit dans `Identity` parce que c'est ce module qui possède le compte et qui doit
 * pouvoir refuser l'activation. Elle lit les modèles d'`Accountability` et de `Work` sans jamais
 * y écrire, conformément à la règle de couplage.
 */
class InternActivationReadiness
{
    /** Nombre d'objectifs exigé avant l'activation (AC 16). */
    public const REQUIRED_OBJECTIVES = 3;

    public function __construct(private readonly AlertLevelService $alertLevelService) {}

    /**
     * Première condition : une fiche d'entrée approuvée existe pour ce compte.
     */
    public function hasApprovedIntakeForm(User $candidate): bool
    {
        return InternshipIntakeForm::query()
            ->where('candidate_user_id', $candidate->getKey())
            ->where('state', InternshipIntakeState::Approuvee)
            ->exists();
    }

    /**
     * Deuxième condition : un tuteur est désigné sur cette fiche approuvée.
     */
    public function hasDesignatedTutor(User $candidate): bool
    {
        return InternshipIntakeForm::query()
            ->where('candidate_user_id', $candidate->getKey())
            ->where('state', InternshipIntakeState::Approuvee)
            ->whereNotNull('tutor_id')
            ->exists();
    }

    /**
     * Troisième condition : trois objectifs au moins sont enregistrés pour ce compte. Un objectif
     * annulé ne compte pas.
     */
    public function hasRequiredObjectives(User $candidate): bool
    {
        return $this->objectiveCount($candidate) >= self::REQUIRED_OBJECTIVES;
    }

    public function objectiveCount(User $candidate): int
    {
        return Objective::query()
            ->where('user_id', $candidate->getKey())
            ->where('state', '!=', ObjectiveState::Annule->value)
            ->count();
    }

    /**
     * Quatrième garde-fou, distinct des trois conditions : le niveau d'alerte rouge bloque
     * l'activation d'un nouveau compte (story 7.1 AC 18, story 9.1 AC 8, FR164).
     *
     * Le calcul réel est livré par l'Epic 9 ; ce point de contrôle n'a pas changé de place.
     */
    public function isAllowedByAlertLevel(): bool
    {
        return $this->alertLevelService->allowsAccountActivation();
    }

    public function isSatisfiedBy(User $candidate): bool
    {
        return $this->missingConditions($candidate) === [];
    }

    /**
     * Conditions manquantes, énoncées une par une pour que le refus soit compréhensible.
     *
     * @return list<string>
     */
    public function missingConditions(User $candidate): array
    {
        $missing = [];

        if (! $this->hasApprovedIntakeForm($candidate)) {
            $missing[] = "la fiche d'entrée n'est pas approuvée";
        }

        if (! $this->hasDesignatedTutor($candidate)) {
            $missing[] = "aucun tuteur n'est désigné";
        }

        if (! $this->hasRequiredObjectives($candidate)) {
            $missing[] = sprintf(
                '%d objectifs sont enregistrés sur les %d attendus',
                $this->objectiveCount($candidate),
                self::REQUIRED_OBJECTIVES,
            );
        }

        if (! $this->isAllowedByAlertLevel()) {
            // Le message nomme le niveau, comme l'exige l'AC 8 de la story 9.1.
            $missing[] = sprintf(
                "le niveau d'alerte financière est %s",
                $this->alertLevelService->current()->label(),
            );
        }

        return $missing;
    }

    /**
     * @return array{approved_intake_form: bool, designated_tutor: bool, required_objectives: bool, objective_count: int, alert_level_allows: bool, satisfied: bool, missing: list<string>}
     */
    public function status(User $candidate): array
    {
        $missing = $this->missingConditions($candidate);

        return [
            'approved_intake_form' => $this->hasApprovedIntakeForm($candidate),
            'designated_tutor' => $this->hasDesignatedTutor($candidate),
            'required_objectives' => $this->hasRequiredObjectives($candidate),
            'objective_count' => $this->objectiveCount($candidate),
            'alert_level_allows' => $this->isAllowedByAlertLevel(),
            'satisfied' => $missing === [],
            'missing' => $missing,
        ];
    }
}
