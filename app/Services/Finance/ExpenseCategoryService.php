<?php

namespace App\Services\Finance;

use App\Models\Finance\ExpenseCategory;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class ExpenseCategoryService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(string $name, bool $isEssential, User $actor): ExpenseCategory
    {
        $category = new ExpenseCategory([
            'name' => trim($name),
            'is_essential' => $isEssential,
        ]);

        return DB::connection($category->getConnectionName())->transaction(function () use ($category, $actor): ExpenseCategory {
            $this->auditLogger->runExplicitly(
                auditable: $category,
                operation: fn (): bool => $category->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'expense_category_created',
                newValues: $category->getAttributes(),
            );

            return $category;
        });
    }

    public function rename(ExpenseCategory $category, string $name, User $actor): ExpenseCategory
    {
        return $this->updateAudited($category, ['name' => trim($name)], $actor, 'expense_category_renamed');
    }

    /** @return Collection<int, ExpenseCategory> */
    public function activeForSelection(): Collection
    {
        return ExpenseCategory::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'is_essential']);
    }

    public function setEssential(ExpenseCategory $category, bool $isEssential, User $actor): ExpenseCategory
    {
        return $this->updateAudited($category, ['is_essential' => $isEssential], $actor, 'expense_category_essential_changed');
    }

    public function deactivate(ExpenseCategory $category, User $actor): ExpenseCategory
    {
        return $this->updateAudited($category, ['is_active' => false], $actor, 'expense_category_deactivated');
    }

    public function reactivate(ExpenseCategory $category, User $actor): ExpenseCategory
    {
        return $this->updateAudited($category, ['is_active' => true], $actor, 'expense_category_reactivated');
    }

    /** @param array<string, string|bool> $attributes */
    private function updateAudited(ExpenseCategory $category, array $attributes, User $actor, string $action): ExpenseCategory
    {
        return DB::connection($category->getConnectionName())->transaction(function () use ($category, $attributes, $actor, $action): ExpenseCategory {
            $lockedCategory = ExpenseCategory::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            $lockedCategory->fill($attributes);
            $changes = $lockedCategory->getDirty();

            if ($changes === []) {
                return $lockedCategory;
            }

            $oldValues = Arr::only($lockedCategory->getRawOriginal(), array_keys($changes));
            $newValues = Arr::only($lockedCategory->getAttributes(), array_keys($changes));

            $this->auditLogger->runExplicitly(
                auditable: $lockedCategory,
                operation: fn (): bool => $lockedCategory->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: $action,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $lockedCategory;
        });
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
