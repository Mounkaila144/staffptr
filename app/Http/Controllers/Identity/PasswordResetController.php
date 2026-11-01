<?php

namespace App\Http\Controllers\Identity;

use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Exceptions\Identity\PasswordResetVerificationFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ConfirmPasswordResetRequest;
use App\Http\Requests\Identity\DirectPasswordResetRequest;
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
            'passwordResetChallenge' => $this->challenge($user),
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
            // Un code erroné laisse le défi en attente : la saisie reste affichée avec son erreur,
            // au lieu d'un retour sur la liste qui obligerait à redemander un code.
            if (! $this->verificationService->isPending($actor, $user)) {
                throw ValidationException::withMessages(['confirmation_code' => $exception->getMessage()]);
            }

            return $this->renderAccounts($actor, [
                'passwordResetChallenge' => $this->challenge($user),
                'errors' => ['confirmation_code' => $exception->getMessage()],
            ]);
        }

        return $this->renderAccounts($actor, [
            'resetAccount' => [
                'person_name' => $this->label($result['user']),
                'phone' => $result['user']->phone,
                'temporary_password' => $result['temporary_password'],
            ],
        ]);
    }

    public function direct(DirectPasswordResetRequest $request, User $user): Response
    {
        $result = $this->passwordResetService->resetWithoutWhatsApp(
            $this->actor($request),
            $user,
            (string) $request->validated('verification'),
        );

        return $this->renderAccounts($this->actor($request), [
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
        // La page est rendue en réponse à un POST : sans cette adresse, l'historique retiendrait
        // l'URL de l'action, qu'un rechargement ou un retour arrière demanderait en GET (405).
        Inertia::resolveUrlUsing(static fn (): string => route('accounts.index', absolute: false));
        $response = Inertia::render('Identity/Accounts/Index', [
            ...$this->accountAdministrationService->indexData($actor, []),
            ...$additionalProps,
        ]);
        Inertia::resolveUrlUsing(null);

        return $response;
    }

    /** @return array{user_id: int, person_name: string, expires_in_minutes: int} */
    private function challenge(User $user): array
    {
        return [
            'user_id' => (int) $user->getKey(),
            'person_name' => $this->label($user),
            'expires_in_minutes' => PasswordResetVerificationService::EXPIRATION_MINUTES,
        ];
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
