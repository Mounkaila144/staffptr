<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Enums\UserState;
use App\Models\Finance\Expense;
use App\Models\Identity\User;

/**
 * Nombre d'approbations exigées pour une dépense (RM-09, décision du propriétaire du 2026-11-01).
 *
 * Requises = 2, sauf quand le demandeur (ou le bénéficiaire d'une part) est lui-même un dirigeant :
 * alors min(2, comptes direction ACTIFS autres que lui), avec un minimum de 1. Une demande d'employé exige donc deux approbations de deux
 * dirigeants différents ; une demande d'un dirigeant, quand l'entreprise n'en compte que deux, se
 * contente de l'approbation de l'autre. Personne n'approuve jamais sa propre dépense.
 *
 * Tant que la dépense est en attente, le nombre est calculé à partir des comptes du moment ; il est
 * figé dans `expenses.approvals_required` au passage à « approuvée », et c'est cette valeur figée
 * que relisent le paiement et le contrôle d'invariants. Une dépense approuvée avant la décision
 * (valeur NULL) en exigeait deux.
 */
class ExpenseApprovalRequirement
{
    public const MAXIMUM = 2;

    public const HISTORICAL_REQUIRED = 2;

    public function requiredFor(Expense $expense): int
    {
        if ($expense->approvals_required !== null) {
            return (int) $expense->approvals_required;
        }

        if (in_array($expense->state, [ExpenseState::Approuvee, ExpenseState::Payee], true)) {
            return self::HISTORICAL_REQUIRED;
        }

        // L'allègement ne vaut que si le demandeur (ou le bénéficiaire) est lui-même un dirigeant
        // qui approuve : c'est lui qui réduit le nombre d'approbateurs possibles. Une demande
        // d'employé exige toujours deux approbations, même si un dirigeant est suspendu.
        if (! $this->involvesAnApprover($expense)) {
            return self::MAXIMUM;
        }

        return max(1, min(self::MAXIMUM, $this->eligibleApproverCount($expense)));
    }

    /** Le demandeur ou le bénéficiaire d'une part est-il lui-même un compte qui approuve ? */
    public function involvesAnApprover(Expense $expense): bool
    {
        $involved = array_values(array_filter([
            (int) $expense->requester_id,
            $expense->beneficiary_user_id !== null ? (int) $expense->beneficiary_user_id : null,
        ]));

        return User::permission('depense.approuver')->whereIn('users.id', $involved)->exists();
    }

    /** Comptes direction actifs qui peuvent approuver : ni le demandeur, ni le bénéficiaire d'une part. */
    public function eligibleApproverCount(Expense $expense): int
    {
        $excluded = array_values(array_filter([
            (int) $expense->requester_id,
            $expense->beneficiary_user_id !== null ? (int) $expense->beneficiary_user_id : null,
        ]));

        return User::permission('depense.approuver')
            ->where('state', UserState::Actif->value)
            ->whereNotIn('users.id', $excluded)
            ->count();
    }

    public function isRequestedByDirection(Expense $expense): bool
    {
        return User::permission('depense.approuver')
            ->whereKey($expense->requester_id)
            ->exists();
    }

    /** Phrase affichée à l'écran : combien d'approbations et pourquoi. */
    public function explanation(Expense $expense, ?int $required = null): string
    {
        $required ??= $this->requiredFor($expense);

        if ($required >= self::MAXIMUM) {
            return 'Deux approbations de deux dirigeants différents sont requises.';
        }

        if ($this->isRequestedByDirection($expense)) {
            return "Demande d'un dirigeant : l'approbation de l'autre dirigeant suffit.";
        }

        return 'Un seul compte de direction actif peut approuver cette dépense (le demandeur et le bénéficiaire en sont exclus) : son approbation suffit.';
    }

    /** Mention inscrite dans l'audit du passage à « approuvée ». */
    public function auditRule(Expense $expense, int $required): string
    {
        return "RM-09 (décision du 2026-11-01) : {$required} approbation".($required > 1 ? 's' : '')
            .' requise'.($required > 1 ? 's' : '')
            .' (2, ou min(2, autres dirigeants actifs) au moins 1 pour une demande d\'un dirigeant). '
            .$this->explanation($expense, $required);
    }

    public function progressLabel(Expense $expense, int $approvedCount, ?int $required = null): string
    {
        $required ??= $this->requiredFor($expense);
        $plural = static fn (int $count): string => $count > 1 ? 's' : '';

        return match ($expense->state) {
            ExpenseState::Approuvee => "Approuvée — {$approvedCount} approbation{$plural($approvedCount)} sur {$required}",
            ExpenseState::Payee => "Payée — {$approvedCount} approbation{$plural($approvedCount)} sur {$required}",
            ExpenseState::Refusee => 'Refusée — une décision de refus suffit',
            ExpenseState::Annulee => 'Annulée',
            default => "Demandée — {$approvedCount} approbation{$plural($approvedCount)} sur {$required}",
        };
    }
}
