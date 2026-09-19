<?php

namespace App\Services\Platform;

use App\Models\Identity\User;
use App\Models\Platform\Holiday;
use App\Support\Auditing\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HolidayService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{label: string, date: string} $attributes */
    public function create(array $attributes, User $actor): Holiday
    {
        $holiday = new Holiday(Arr::only($attributes, ['label', 'date']));

        return DB::connection($holiday->getConnectionName())->transaction(function () use ($holiday, $actor): Holiday {
            $this->auditLogger->runExplicitly(
                auditable: $holiday,
                operation: fn (): bool => $holiday->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'holiday_created',
                newValues: $holiday->getAttributes(),
            );

            return $holiday;
        });
    }

    /** @param array{label: string, date: string} $attributes */
    public function update(Holiday $holiday, array $attributes, User $actor): Holiday
    {
        return $this->updateAudited($holiday, $attributes, $actor, 'holiday_updated');
    }

    public function deactivate(Holiday $holiday, User $actor): Holiday
    {
        return $this->updateAudited($holiday, ['is_active' => false], $actor, 'holiday_deactivated');
    }

    public function reactivate(Holiday $holiday, User $actor): Holiday
    {
        return $this->updateAudited($holiday, ['is_active' => true], $actor, 'holiday_reactivated');
    }

    /** @param array<string, mixed> $attributes */
    private function updateAudited(Holiday $holiday, array $attributes, User $actor, string $action): Holiday
    {
        return DB::connection($holiday->getConnectionName())->transaction(function () use ($holiday, $attributes, $actor, $action): Holiday {
            $lockedHoliday = Holiday::query()->whereKey($holiday->getKey())->lockForUpdate()->firstOrFail();
            $lockedHoliday->fill($attributes);
            $changes = $lockedHoliday->getDirty();

            if ($changes === []) {
                return $lockedHoliday;
            }

            $oldValues = Arr::only($lockedHoliday->getRawOriginal(), array_keys($changes));
            $newValues = Arr::only($lockedHoliday->getAttributes(), array_keys($changes));

            $this->auditLogger->runExplicitly(
                auditable: $lockedHoliday,
                operation: fn (): bool => $lockedHoliday->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: $action,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $lockedHoliday;
        });
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
