<?php

namespace App\Policies\Platform;

use App\Models\Identity\User;
use App\Models\Platform\Holiday;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesCalendar($user);
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $this->managesCalendar($user);
    }

    public function create(User $user): bool
    {
        return $this->managesCalendar($user);
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $this->managesCalendar($user);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return false;
    }

    public function restore(User $user, Holiday $holiday): bool
    {
        return false;
    }

    public function forceDelete(User $user, Holiday $holiday): bool
    {
        return false;
    }

    private function managesCalendar(User $user): bool
    {
        return $user->hasRole('direction') && $user->can('calendrier.gerer');
    }
}
