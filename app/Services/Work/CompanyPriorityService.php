<?php

namespace App\Services\Work;

use App\Enums\CompanyPriorityState;
use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;
use App\Support\Auditing\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompanyPriorityService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): CompanyPriority
    {
        $priority = new CompanyPriority;

        return DB::connection($priority->getConnectionName())->transaction(function () use ($priority, $data, $actor): CompanyPriority {
            $month = CarbonImmutable::parse((string) $data['month'], 'Africa/Niamey')->startOfMonth();
            $this->lockAndAssertMonthlyLimit($month);
            $priority->fill([...$data, 'month' => $month->toDateString(), 'state' => CompanyPriorityState::Validee]);
            $this->auditLogger->runExplicitly($priority, fn (): bool => $priority->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'company_priority_created', newValues: $priority->getAttributes());

            return $priority->load('owner.person');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CompanyPriority $priority, array $data, string $reason, User $actor): CompanyPriority
    {
        return DB::connection($priority->getConnectionName())->transaction(function () use ($priority, $data, $reason, $actor): CompanyPriority {
            $locked = CompanyPriority::query()->whereKey($priority->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state === CompanyPriorityState::Validee && trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Le motif est obligatoire pour modifier une priorité validée.']);
            }
            $fields = ['title', 'description', 'owner_id', 'indicator', 'target', 'due_date', 'priority'];
            $old = Arr::only($locked->getAttributes(), $fields);
            $locked->fill(Arr::only($data, $fields));
            $new = Arr::only($locked->getAttributes(), $fields);
            $this->auditLogger->runExplicitly($locked, fn (): bool => $locked->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'company_priority_updated', $old, $new, $reason);

            return $locked->refresh()->load('owner.person');
        });
    }

    public function cancel(CompanyPriority $priority, string $reason, User $actor): CompanyPriority
    {
        return DB::connection($priority->getConnectionName())->transaction(function () use ($priority, $reason, $actor): CompanyPriority {
            $locked = CompanyPriority::query()->whereKey($priority->getKey())->lockForUpdate()->firstOrFail();
            $old = ['state' => $locked->state->value, 'cancellation_reason' => $locked->cancellation_reason];
            $locked->fill(['state' => CompanyPriorityState::Annulee, 'cancellation_reason' => $reason]);
            $this->auditLogger->runExplicitly($locked, fn (): bool => $locked->saveOrFail(), $actor->getKey(), $this->actorLabel($actor), 'company_priority_cancelled', $old, ['state' => CompanyPriorityState::Annulee->value, 'cancellation_reason' => $reason], $reason);

            return $locked->refresh();
        });
    }

    private function lockAndAssertMonthlyLimit(CarbonImmutable $month): void
    {
        $lockedPriorities = CompanyPriority::query()->forMonth($month)->where('state', CompanyPriorityState::Validee)->lockForUpdate()->get(['id']);
        $count = 0;
        foreach ($lockedPriorities as $lockedPriority) {
            $count++;
        }
        if ($count >= 5) {
            $label = $month->locale('fr')->isoFormat('MMMM YYYY');
            throw ValidationException::withMessages(['month' => "La limite de 5 priorités validées pour {$label} est atteinte."]);
        }
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
