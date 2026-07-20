<?php

namespace App\Http\Controllers\Identity;

use App\Enums\UserState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\LoginRequest;
use App\Models\Identity\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticationController extends Controller
{
    private const INVALID_CREDENTIALS_MESSAGE = 'Numéro ou mot de passe incorrect.';

    private const INACTIVE_ACCOUNT_MESSAGE = "Votre compte n'est pas actif. Contactez la direction.";

    public function create(): Response
    {
        return Inertia::render('Identity/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['phone', 'password']);
        $inactiveAccount = false;

        $authenticated = Auth::attemptWhen(
            $credentials,
            static function (User $user) use (&$inactiveAccount): bool {
                $inactiveAccount = $user->state !== UserState::Actif;

                return ! $inactiveAccount;
            },
        );

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'phone' => $inactiveAccount
                    ? self::INACTIVE_ACCOUNT_MESSAGE
                    : self::INVALID_CREDENTIALS_MESSAGE,
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
