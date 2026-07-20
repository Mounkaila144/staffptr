<?php

namespace App\Services\Identity;

use App\Models\Identity\Person;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;

class IdentityVisibility
{
    /** @return Builder<Person> */
    public function peopleIndex(User $viewer): Builder
    {
        return Person::query()->visibleTo($viewer);
    }

    /** @return Builder<Person> */
    public function peopleExport(User $viewer): Builder
    {
        return $this->peopleIndex($viewer);
    }

    /** @return Builder<User> */
    public function usersIndex(User $viewer): Builder
    {
        return User::query()->visibleTo($viewer);
    }

    /** @return Builder<User> */
    public function usersExport(User $viewer): Builder
    {
        return $this->usersIndex($viewer);
    }
}
