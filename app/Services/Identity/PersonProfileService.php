<?php

namespace App\Services\Identity;

use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PersonProfileService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly HierarchyService $hierarchyService,
        private readonly UserHistoryService $userHistoryService,
    ) {}

    public function currentAccount(Person $person): User
    {
        $account = $this->currentAccountOrNull($person);

        if ($account === null) {
            throw (new ModelNotFoundException)->setModel(User::class);
        }

        return $account;
    }

    public function currentAccountOrNull(Person $person): ?User
    {
        $active = $person->users()
            ->where('state', UserState::Actif)
            ->latest('id')
            ->first();

        return $active ?? $person->users()
            ->where('state', '!=', UserState::Archive)
            ->latest('id')
            ->first();
    }

    /**
     * @param array{
     *   full_name: string,
     *   photo_path?: string|null,
     *   phone: string,
     *   department_id?: int|null,
     *   job_function_id?: int|null,
     *   manager_id?: int|null,
     *   relation_type: string,
     *   contract_start_date?: string|null,
     *   contract_end_date?: string|null
     * } $attributes
     */
    public function update(Person $person, User $account, array $attributes, User $actor): User
    {
        return DB::connection($account->getConnectionName())->transaction(function () use (
            $person,
            $account,
            $attributes,
            $actor,
        ): User {
            $lockedPerson = Person::query()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();
            $lockedAccount = User::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $managerId = Arr::get($attributes, 'manager_id');
            $resolvedManagerId = is_numeric($managerId) ? (int) $managerId : null;
            $oldDepartmentId = is_numeric($lockedAccount->getRawOriginal('department_id'))
                ? (int) $lockedAccount->getRawOriginal('department_id')
                : null;
            $departmentId = Arr::get($attributes, 'department_id');
            $resolvedDepartmentId = is_numeric($departmentId) ? (int) $departmentId : null;
            $oldManagerId = is_numeric($lockedAccount->getRawOriginal('manager_id'))
                ? (int) $lockedAccount->getRawOriginal('manager_id')
                : null;

            $this->hierarchyService->assertManagerAssignmentAllowed($lockedAccount, $resolvedManagerId);

            $this->updateAudited(
                $lockedPerson,
                Arr::only($attributes, ['full_name', 'photo_path']),
                $actor,
                'person_profile_updated',
            );
            $this->updateAudited(
                $lockedAccount,
                Arr::only($attributes, [
                    'phone',
                    'department_id',
                    'job_function_id',
                    'relation_type',
                    'contract_start_date',
                    'contract_end_date',
                ]),
                $actor,
                'user_profile_updated',
            );

            if (array_key_exists('department_id', $attributes) && $oldDepartmentId !== $resolvedDepartmentId) {
                $this->userHistoryService->record(
                    user: $lockedAccount,
                    field: 'department_id',
                    oldValue: $this->userHistoryService->departmentSnapshot($oldDepartmentId),
                    newValue: $this->userHistoryService->departmentSnapshot($resolvedDepartmentId),
                    actor: $actor,
                );
            }

            if (array_key_exists('manager_id', $attributes)) {
                $this->updateAudited(
                    $lockedAccount,
                    ['manager_id' => $resolvedManagerId],
                    $actor,
                    'manager_changed',
                );

                if ($oldManagerId !== $resolvedManagerId) {
                    $this->userHistoryService->record(
                        user: $lockedAccount,
                        field: 'manager_id',
                        oldValue: $this->userHistoryService->managerSnapshot($oldManagerId),
                        newValue: $this->userHistoryService->managerSnapshot($resolvedManagerId),
                        actor: $actor,
                    );
                }
            }

            return $lockedAccount->refresh();
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function updateAudited(Model $model, array $attributes, User $actor, string $action): Model
    {
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
            actorId: $actor->getKey(),
            actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
        );

        return $model;
    }
}
