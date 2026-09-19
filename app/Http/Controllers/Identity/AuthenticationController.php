<?php

namespace App\Http\Controllers\Identity;

use App\Enums\LoginAuthenticationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\LoginRequest;
use App\Services\Identity\LoginAttemptService;
use App\Services\Identity\LoginSecuritySettings;
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

    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
        private readonly LoginSecuritySettings $loginSecuritySettings,
    ) {}

    public function create(): Response
    {
        return Inertia::render('Identity/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $result = $this->loginAttemptService->attempt(
            phoneAttempted: (string) $request->validated('phone'),
            password: (string) $request->validated('password'),
            ipAddress: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        if ($result->status !== LoginAuthenticationStatus::Authenticated) {
            throw ValidationException::withMessages([
                'phone' => match ($result->status) {
                    LoginAuthenticationStatus::Blocked => $this->loginSecuritySettings->blockedMessage(),
                    LoginAuthenticationStatus::InactiveAccount => self::INACTIVE_ACCOUNT_MESSAGE,
                    default => self::INVALID_CREDENTIALS_MESSAGE,
                },
            ]);
        }

        Auth::login($result->user);
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
