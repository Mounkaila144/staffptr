<?php

namespace App\Policies\Accountability;

use App\Models\Accountability\DailyReportVersion;
use App\Models\Identity\User;

class DailyReportVersionPolicy
{
    public function view(User $user, DailyReportVersion $version): bool
    {
        return $user->can('view', $version->report);
    }

    public function update(User $user, DailyReportVersion $version): bool
    {
        return false;
    }

    public function delete(User $user, DailyReportVersion $version): bool
    {
        return false;
    }
}
