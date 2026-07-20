<?php

namespace App\Services\Identity;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IdentityService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

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
        return $this->updateAudited(
            $person,
            ['operational_status' => $status],
            $actorId,
            $actorLabel,
            'person_status_changed',
            $reason,
        );
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
     *   password?: string,
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
                'password',
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
        return $this->updateAudited(
            $user,
            ['state' => $state],
            $actorId,
            $actorLabel,
            'user_state_changed',
            $reason,
        );
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
     * @return TModel
     */
    private function updateAudited(
        Model $model,
        array $attributes,
        ?int $actorId,
        string $actorLabel,
        string $action,
        ?string $reason,
    ): Model {
        return DB::connection($model->getConnectionName())->transaction(function () use (
            $model,
            $attributes,
            $actorId,
            $actorLabel,
            $action,
            $reason,
        ): Model {
            $model->fill($attributes);
            $changes = $model->getDirty();

            if ($changes === []) {
                return $model;
            }

            $oldValues = Arr::only($model->getRawOriginal(), array_keys($changes));
            $newValues = Arr::only($model->getAttributes(), array_keys($changes));

            $this->auditLogger->runExplicitly(
                auditable: $model,
                operation: fn (): bool => $model->saveOrFail(),
                actorId: $actorId,
                actorLabel: $actorLabel,
                action: $action,
                oldValues: $oldValues,
                newValues: $newValues,
                reason: $reason,
            );

            return $model;
        });
    }
}
