<?php

namespace App\Services\Identity;

use App\Models\Identity\User;

class ExpenseApprovalReadiness
{
    public const UNAVAILABLE_MESSAGE = 'Seul votre compte existe. Créez les deux comptes de direction pour rendre les dépenses approuvables.';

    public function approvalAccountCount(): int
    {
        return User::permission('depense.approuver')->count();
    }

    public function isApprovalAvailable(): bool
    {
        return $this->approvalAccountCount() === 2;
    }

    /** @return array{approval_account_count: int, approval_available: bool, message: string|null} */
    public function status(): array
    {
        $count = $this->approvalAccountCount();
        $available = $count === 2;

        return [
            'approval_account_count' => $count,
            'approval_available' => $available,
            'message' => $available ? null : self::UNAVAILABLE_MESSAGE,
        ];
    }
}
