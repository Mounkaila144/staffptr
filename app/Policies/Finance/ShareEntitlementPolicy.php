<?php

namespace App\Policies\Finance;

use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;

class ShareEntitlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('part.consulter');
    }

    public function view(User $user, ShareEntitlement $share): bool
    {
        return $this->viewAny($user)
            && ShareEntitlement::query()->visibleTo($user)->whereKey($share->getKey())->exists();
    }

    public function requestPayment(User $user, ShareEntitlement $share): bool
    {
        return $this->view($user, $share)
            && (int) $share->beneficiary_id === (int) $user->getKey()
            && $share->remainingAmount() > 0
            && $share->reversed_at === null;
    }

    public function delete(User $user, ShareEntitlement $share): bool
    {
        return false;
    }
}
