<?php

namespace App\Services\Identity;

use App\Models\Identity\Company;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{name?: string, phone?: string|null, email?: string|null, address?: string|null, logo_path?: string|null} $attributes */
    public function updateCompany(Company $company, array $attributes, User $actor): Company
    {
        return $this->updateAudited(
            $company,
            Arr::only($attributes, ['name', 'phone', 'email', 'address', 'logo_path']),
            $actor,
            'company_updated',
        );
    }

    public function createDepartment(string $name, User $actor): Department
    {
        return $this->createAudited(new Department(['name' => $name]), $actor, 'department_created');
    }

    public function renameDepartment(Department $department, string $name, User $actor): Department
    {
        return $this->updateAudited($department, ['name' => $name], $actor, 'department_renamed');
    }

    public function deactivateDepartment(Department $department, User $actor): Department
    {
        return DB::connection($department->getConnectionName())->transaction(function () use ($department, $actor): Department {
            $lockedDepartment = Department::query()->whereKey($department->getKey())->lockForUpdate()->firstOrFail();
            $membersCount = $lockedDepartment->activeMembersCount();

            if ($membersCount > 0) {
                $noun = $membersCount > 1 ? 'membres' : 'membre';
                $pronoun = $membersCount > 1 ? 'les' : 'le';

                throw ValidationException::withMessages([
                    'department' => "Ce service compte encore {$membersCount} {$noun}. Réaffectez-{$pronoun} avant de le désactiver.",
                ]);
            }

            return $this->updateAuditedInCurrentTransaction(
                $lockedDepartment,
                ['is_active' => false],
                $actor,
                'department_deactivated',
            );
        });
    }

    public function reactivateDepartment(Department $department, User $actor): Department
    {
        return $this->updateAudited($department, ['is_active' => true], $actor, 'department_reactivated');
    }

    public function createJobFunction(string $name, User $actor): JobFunction
    {
        return $this->createAudited(new JobFunction(['name' => $name]), $actor, 'job_function_created');
    }

    public function renameJobFunction(JobFunction $jobFunction, string $name, User $actor): JobFunction
    {
        return $this->updateAudited($jobFunction, ['name' => $name], $actor, 'job_function_renamed');
    }

    public function deactivateJobFunction(JobFunction $jobFunction, User $actor): JobFunction
    {
        return $this->updateAudited($jobFunction, ['is_active' => false], $actor, 'job_function_deactivated');
    }

    public function reactivateJobFunction(JobFunction $jobFunction, User $actor): JobFunction
    {
        return $this->updateAudited($jobFunction, ['is_active' => true], $actor, 'job_function_reactivated');
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function createAudited(Model $model, User $actor, string $action): Model
    {
        return DB::connection($model->getConnectionName())->transaction(function () use ($model, $actor, $action): Model {
            $this->auditLogger->runExplicitly(
                auditable: $model,
                operation: fn (): bool => $model->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: $action,
                newValues: $model->getAttributes(),
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
    private function updateAudited(Model $model, array $attributes, User $actor, string $action): Model
    {
        return DB::connection($model->getConnectionName())->transaction(
            fn (): Model => $this->updateAuditedInCurrentTransaction($model, $attributes, $actor, $action),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    private function updateAuditedInCurrentTransaction(Model $model, array $attributes, User $actor, string $action): Model
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
            actorLabel: $this->actorLabel($actor),
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
        );

        return $model;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
