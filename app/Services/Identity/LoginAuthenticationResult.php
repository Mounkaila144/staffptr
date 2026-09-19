<?php

namespace App\Services\Identity;

use App\Enums\LoginAuthenticationStatus;
use App\Models\Identity\User;

final readonly class LoginAuthenticationResult
{
    public function __construct(
        public LoginAuthenticationStatus $status,
        public ?User $user = null,
    ) {}
}
