<?php

namespace App\Http\Controllers\Identity;

use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Exceptions\Identity\PasswordResetVerificationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ConfirmPasswordResetRequest;
use App\Http\Requests\Identity\InitiatePasswordResetRequest;
use App\Models\Identity\User;
use App\Services\Identity\AccountAdministrationService;
use App\Services\Identity\PasswordResetService;
use App\Services\Identity\PasswordResetVerificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AccountAdministrationService $accountAdministrationService,
        private readonly PasswordResetVerificationService $verificationService,
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function initiate(InitiatePasswordResetRequest $request, User $user): Response
    {
        Gate::authorize('update', $user);
        $actor = $this->actor($request);

        try {
            $this->verificationService->initiate($actor, $user);
        } catch (EvolutionApiUnavailable $exception) {
            throw ValidationException::withMessages(['password_reset' => $exception->getMessage()]);
        }

        return $this->renderAccounts($actor, [
            'passwordResetChallenge' => [
                'user_id' => (int) $user->getKey(),
                'person_name' => $this->label($user),
                'expires_in_minutes' => PasswordResetVerificationService::EXPIRATION_MINUTES,
            ],
        ]);
    }

    public function confirm(ConfirmPasswordResetRequest $request, User $user): Response
    {
        Gate::authorize('update', $user);
        $actor = $this->actor($request);

        try {
            $result = $this->passwordResetService->reset(
                $actor,
                $user,
                (string) $request->validated('confirmation_code'),
            );
        } catch (PasswordResetVerificationFailed $exception) {
            throw ValidationException::withMessages(['confirmation_code' => $exception->getMessage()]);
        }

        return $this->renderAccounts($actor, [
            'resetAccount' => [
                'person_name' => $this->label($result['user']),
                'phone' => $result['user']->phone,
                'temporary_password' => $result['temporary_password'],
            ],
        ]);
    }

    /** @param array<string, mixed> $additionalProps */
    private function renderAccounts(User $actor, array $additionalProps): Response
    {
        return Inertia::render('Identity/Accounts/Index', [
            ...$this->accountAdministrationService->indexData($actor, []),
            ...$additionalProps,
        ]);
    }

    private function actor(FormRequest $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function label(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
