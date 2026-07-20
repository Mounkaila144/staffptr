<?php

namespace App\Enums;

enum LoginAuthenticationStatus: string
{
    case Authenticated = 'authenticated';
    case InvalidCredentials = 'invalid_credentials';
    case InactiveAccount = 'inactive_account';
    case Blocked = 'blocked';
}
