<?php

namespace App\Policies\Finance;

use App\Models\Finance\MonthlyReport;
use App\Models\Identity\User;

class MonthlyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('rapport_financier.consulter');
    }

    public function view(User $user, MonthlyReport $report): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('finance') && $user->can('rapport_financier.preparer');
    }

    public function control(User $user, MonthlyReport $report): bool
    {
        return $user->hasAnyRole(['direction', 'finance']) && $user->can('rapport_financier.controler');
    }

    public function validate(User $user, MonthlyReport $report): bool
    {
        return $user->hasRole('direction') && $user->can('rapport_financier.valider');
    }

    public function reopen(User $user, MonthlyReport $report): bool
    {
        return $this->validate($user, $report);
    }

    public function update(User $user, MonthlyReport $report): bool
    {
        return false;
    }

    public function delete(User $user, MonthlyReport $report): bool
    {
        return false;
    }
}
