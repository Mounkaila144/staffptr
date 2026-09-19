<?php

namespace App\Services\Identity;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Finance\AlertLevelService;
use App\Support\Auditing\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdentityService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SessionRevocationService $sessionRevocationService,
        private readonly UserHistoryService $userHistoryService,
        private readonly PersonProfileService $personProfileService,
    ) {}

    /** @param array{full_name: string, operational_status?: PersonOperationalStatus|string, first_seen_at: string} $attributes */
    public function createPerson(
        array $attributes,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): Person {
        $person = new Person($attributes);

        return $this->createAudited($person, $actorId, $actorLabel, 'person_created', $reason);
    }

    /** @param array{full_name?: string, first_seen_at?: string} $attributes */
    public function updatePerson(
        Person $person,
        array $attributes,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): Person {
        return $this->updateAudited(
            $person,
            Arr::only($attributes, ['full_name', 'first_seen_at']),
            $actorId,
            $actorLabel,
            'person_updated',
            $reason,
        );
    }

    public function changePersonStatus(
        Person $person,
        PersonOperationalStatus $status,
        ?int $actorId,
        string $actorLabel,
        string $reason,
    ): Person {
        return DB::connection($person->getConnectionName())->transaction(function () use (
            $person,
            $status,
            $actorId,
            $actorLabel,
            $reason,
        ): Person {
            $oldStatus = $person->operational_status;
            $updatedPerson = $this->updateAudited(
                $person,
                ['operational_status' => $status],
                $actorId,
                $actorLabel,
                'person_status_changed',
                $reason,
            );

            if ($oldStatus !== $status) {
                $account = $this->personProfileService->currentAccountOrNull($person);

                if ($account !== null) {
                    $this->userHistoryService->record(
                        user: $account,
                        field: 'operational_status',
                        oldValue: $this->userHistoryService->statusSnapshot($oldStatus),
                        newValue: $this->userHistoryService->statusSnapshot($status),
                        actor: $this->userHistoryService->actor($actorId),
                        reason: $reason,
                    );
                }
            }

            return $updatedPerson;
        });
    }

    /**
     * @param array{
     *   phone: string,
     *   password: string,
     *   state?: UserState|string,
     *   must_change_password?: bool,
     *   locked_until?: string|null,
     *   failed_attempts?: int
     * } $attributes
     */
    public function createUser(
        Person $person,
        array $attributes,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): User {
        $user = $person->users()->make($attributes);

        return $this->createAudited($user, $actorId, $actorLabel, 'user_created', $reason);
    }

    /**
     * @param array{
     *   phone?: string,
     *   must_change_password?: bool,
     *   locked_until?: string|null,
     *   failed_attempts?: int
     * } $attributes
     */
    public function updateUser(
        User $user,
        array $attributes,
        ?int $actorId,
        string $actorLabel,
        ?string $reason = null,
    ): User {
        return $this->updateAudited(
            $user,
            Arr::only($attributes, [
                'phone',
                'must_change_password',
                'locked_until',
                'failed_attempts',
            ]),
            $actorId,
            $actorLabel,
            'user_updated',
            $reason,
        );
    }

    public function changeUserState(
        User $user,
        UserState $state,
        ?int $actorId,
        string $actorLabel,
        string $reason,
    ): User {
        $this->assertAlertLevelAllowsActivation($user, $state, $actorId, $actorLabel);
        $this->assertInternMayBecomeActive($user, $state);

        return $this->updateAudited(
            $user,
            ['state' => $state],
            $actorId,
            $actorLabel,
            'user_state_changed',
            $reason,
            $state === UserState::Suspendu
                ? fn (): array => [
                    'sessions_revoked' => $this->sessionRevocationService->revokeFor($user),
                ]
                : null,
        );
    }

    /**
     * Effet borné du niveau d'alerte rouge : **l'activation d'un nouveau compte employé ou
     * stagiaire est refusée** côté serveur (story 9.1 AC 8, FR164).
     *
     * « Nouveau » se lit littéralement : seul le passage `invite → actif`, c'est-à-dire la
     * première activation, est concerné. La réactivation d'un compte suspendu ou terminé reste
     * possible en rouge, parce que le niveau d'alerte peut bloquer une écriture mais **jamais une
     * personne** (AC 12, RM-18, P3). Aucun état, rôle, permission ni session n'est modifié ici.
     *
     * Le contrôle est posé dans le service propriétaire du compte pour qu'aucun chemin
     * d'activation — administration des comptes ou activation de stagiaire — ne le contourne
     * (règle de couplage de `source-tree.md`).
     *
     * L'audit de l'effet (AC 13) est écrit dans sa propre transaction, validée avant le refus :
     * une trace du refus doit survivre au rejet de l'opération.
     */
    private function assertAlertLevelAllowsActivation(
        User $user,
        UserState $state,
        ?int $actorId,
        string $actorLabel,
    ): void {
        if ($state !== UserState::Actif || $user->state !== UserState::Invite) {
            return;
        }

        if (! $user->hasRole('employe') && ! $user->hasRole('stagiaire')) {
            return;
        }

        $level = app(AlertLevelService::class)->current();

        if (! $level->blocksAccountActivation()) {
            return;
        }

        DB::connection($user->getConnectionName())->transaction(function () use ($user, $level, $actorId, $actorLabel): void {
            $this->auditLogger->record(
                actorId: $actorId,
                actorLabel: $actorLabel,
                auditable: $user,
                action: 'account_activation_refused_by_alert',
                newValues: ['alert_level' => $level->value, 'requested_state' => UserState::Actif->value],
                reason: "Activation refusée par le niveau d'alerte financière.",
            );
        });

        throw ValidationException::withMessages([
            'state' => sprintf(
                "Ce compte ne peut pas être activé : le niveau d'alerte financière est %s. Aucune nouvelle activation n'est possible tant que la situation n'est pas revenue au vert ou à l'orange.",
                $level->label(),
            ),
        ]);
    }

    /**
     * Aucun compte `stagiaire` ne passe à `actif` sans fiche d'entrée approuvée, tuteur désigné
     * et trois objectifs enregistrés (AC 16, 41).
     *
     * Le contrôle est posé ici, dans le service propriétaire du compte, pour qu'aucun chemin
     * d'activation ne puisse le contourner.
     */
    private function assertInternMayBecomeActive(User $user, UserState $state): void
    {
        if ($state !== UserState::Actif || ! $user->hasRole('stagiaire')) {
            return;
        }

        $readiness = app(InternActivationReadiness::class);
        $missing = $readiness->missingConditions($user);

        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'state' => sprintf(
                'Ce compte de stagiaire ne peut pas être activé : %s.',
                implode(', ', $missing),
            ),
        ]);
    }

    public function changePassword(
        User $user,
        string $password,
        ?int $actorId,
        string $actorLabel,
        string $reason,
    ): User {
        return $this->updateAudited(
            $user,
            [
                'password' => $password,
                'must_change_password' => false,
            ],
            $actorId,
            $actorLabel,
            'password_changed',
            $reason,
            fn (): array => [
                'sessions_revoked' => $this->sessionRevocationService->revokeFor($user),
            ],
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function createAudited(
        Model $model,
        ?int $actorId,
        string $actorLabel,
        string $action,
        ?string $reason,
    ): Model {
        return DB::connection($model->getConnectionName())->transaction(function () use (
            $model,
            $actorId,
            $actorLabel,
            $action,
            $reason,
        ): Model {
            $this->auditLogger->runExplicitly(
                auditable: $model,
                operation: fn (): bool => $model->saveOrFail(),
                actorId: $actorId,
                actorLabel: $actorLabel,
                action: $action,
                newValues: $model->getAttributes(),
                reason: $reason,
            );

            return $model;
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @param  (Closure(): array<string, mixed>)|null  $afterUpdate
     * @return TModel
     */
    private function updateAudited(
        Model $model,
        array $attributes,
        ?int $actorId,
        string $actorLabel,
        string $action,
        ?string $reason,
        ?Closure $afterUpdate = null,
    ): Model {
        return DB::connection($model->getConnectionName())->transaction(function () use (
            $model,
            $attributes,
            $actorId,
            $actorLabel,
            $action,
            $reason,
            $afterUpdate,
        ): Model {
            $model->fill($attributes);
            $changes = $model->getDirty();

            if ($changes === []) {
                return $model;
            }

            $oldValues = Arr::only($model->getRawOriginal(), array_keys($changes));
            $newValues = Arr::only($model->getAttributes(), array_keys($changes));

            $operationMetadata = $afterUpdate !== null ? $afterUpdate() : [];

            $this->auditLogger->runExplicitly(
                auditable: $model,
                operation: fn (): bool => $model->saveOrFail(),
                actorId: $actorId,
                actorLabel: $actorLabel,
                action: $action,
                oldValues: $oldValues,
                newValues: [...$newValues, ...$operationMetadata],
                reason: $reason,
            );

            return $model;
        });
    }
}
