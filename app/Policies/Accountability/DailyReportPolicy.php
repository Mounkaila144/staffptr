<?php

namespace App\Policies\Accountability;

use App\Models\Accountability\DailyReport;
use App\Models\Identity\User;

class DailyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rapport_quotidien.consulter');
    }

    public function view(User $user, DailyReport $dailyReport): bool
    {
        return $user->can('rapport_quotidien.consulter')
            && ($dailyReport->author_id === $user->getKey()
                || $user->hasRole('direction')
                || $dailyReport->author()->where('manager_id', $user->getKey())->exists());
    }

    public function create(User $user): bool
    {
        return $user->can('rapport_quotidien.creer');
    }

    public function update(User $user, DailyReport $dailyReport): bool
    {
        return $user->can('rapport_quotidien.creer') && $dailyReport->author_id === $user->getKey();
    }

    public function review(User $user, DailyReport $dailyReport): bool
    {
        if (! $user->can('rapport_quotidien.valider') || $dailyReport->author_id === $user->getKey()) {
            return false;
        }

        return $user->hasRole('direction')
            || $dailyReport->author()->where('manager_id', $user->getKey())->exists();
    }

    public function delete(User $user, DailyReport $dailyReport): bool
    {
        return false;
    }
}
