<?php

namespace App\Services\Identity;

use App\Exceptions\Identity\PasswordResetVerificationFailed;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Facades\DB;

class PasswordResetService
{
    public function __construct(
        private readonly PasswordResetVerificationService $verificationService,
        private readonly TemporaryPasswordGenerator $temporaryPasswordGenerator,
        private readonly LoginAttemptService $loginAttemptService,
        private readonly SessionRevocationService $sessionRevocationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{user: User, temporary_password: string}
     *
     * @throws PasswordResetVerificationFailed
     */
    public function reset(User $actor, User $target, string $confirmationCode): array
    {
        $this->verificationService->consume($actor, $target, $confirmationCode);
        $temporaryPassword = $this->temporaryPasswordGenerator->generate();

        return DB::connection($target->getConnectionName())->transaction(function () use ($actor, $target, $temporaryPassword): array {
            $actorLabel = $this->label($actor);
            $targetLabel = $this->label($target);
            $lockedTarget = $this->loginAttemptService->clearPersistentLockForPasswordReset(
                $target,
                (int) $actor->getKey(),
                $actorLabel,
                $targetLabel,
            );

            $this->auditLogger->runExplicitly(
                auditable: $lockedTarget,
                operation: function () use ($lockedTarget, $temporaryPassword): void {
                    $lockedTarget->forceFill([
                        'password' => $temporaryPassword,
                        'must_change_password' => true,
                    ])->saveOrFail();
                    $this->sessionRevocationService->revokeFor($lockedTarget);
                },
                actorId: (int) $actor->getKey(),
                actorLabel: $actorLabel,
                action: 'password_reset_by_administrator',
                oldValues: ['must_change_password' => (bool) $target->must_change_password, 'target_label' => $targetLabel],
                newValues: [
                    'must_change_password' => true,
                    'target_label' => $targetLabel,
                    'identity_verification' => 'Code WhatsApp confirmé sur le numéro enregistré.',
                ],
                reason: "Mot de passe de {$targetLabel} réinitialisé par {$actorLabel} après confirmation du code WhatsApp.",
            );

            return ['user' => $lockedTarget, 'temporary_password' => $temporaryPassword];
        });
    }

    private function label(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}
