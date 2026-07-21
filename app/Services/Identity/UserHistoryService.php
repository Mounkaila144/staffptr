<?php

namespace App\Services\Identity;

use App\Enums\PersonOperationalStatus;
use App\Models\Identity\Department;
use App\Models\Identity\User;
use App\Models\Identity\UserHistory;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use RuntimeException;

class UserHistoryService
{
    public function record(
        User $user,
        string $field,
        ?string $oldValue,
        ?string $newValue,
        ?User $actor,
        ?string $reason = null,
    ): ?UserHistory {
        if ($oldValue === $newValue) {
            return null;
        }

        if ($user->getConnection()->transactionLevel() < 1) {
            throw new RuntimeException("L'historique de fiche doit être écrit dans la transaction métier active.");
        }

        return UserHistory::query()->create([
            'user_id' => $user->getKey(),
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => $actor?->getKey(),
            'changed_at' => CarbonImmutable::now('UTC'),
            'reason' => $reason,
        ]);
    }

    public function actor(?int $actorId): ?User
    {
        return $actorId === null ? null : User::query()->find($actorId);
    }

    /** @param list<string> $roles */
    public function rolesSnapshot(array $roles): string
    {
        if ($roles === []) {
            return 'Aucun rôle';
        }

        $labels = array_map(fn (string $role): string => $this->roleLabel($role), $roles);
        sort($labels);

        return implode(', ', $labels);
    }

    public function departmentSnapshot(?int $departmentId): ?string
    {
        if ($departmentId === null) {
            return null;
        }

        return Department::query()->whereKey($departmentId)->value('name') ?? "Service #{$departmentId}";
    }

    public function managerSnapshot(?int $managerId): ?string
    {
        if ($managerId === null) {
            return null;
        }

        $manager = User::query()->with('person')->find($managerId);

        return $manager?->person->full_name ?? "Compte #{$managerId}";
    }

    public function statusSnapshot(PersonOperationalStatus|string $status): string
    {
        $resolved = is_string($status) ? PersonOperationalStatus::from($status) : $status;

        return $resolved->label();
    }

    /** @return array<string, mixed> */
    public function forDisplay(User $user): array
    {
        return $user->history()
            ->with('changedBy.person')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UserHistory $history): array => [
                'id' => (int) $history->getKey(),
                'field' => $history->field,
                'field_label' => $this->fieldLabel($history->field),
                'old_value' => $history->old_value ?? 'Non renseigné',
                'new_value' => $history->new_value ?? 'Non renseigné',
                'author' => $history->changedBy?->person->full_name ?? 'Système',
                'changed_at' => DateTimeFormatter::format($history->changed_at),
                'reason' => $history->reason,
            ])
            ->toArray();
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'roles' => 'Rôles',
            'department_id' => 'Service',
            'manager_id' => 'Responsable',
            'operational_status' => 'Statut',
            default => $field,
        };
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'direction' => 'Direction',
            'finance' => 'Finance',
            'tuteur' => 'Tuteur',
            'employe' => 'Employé',
            'stagiaire' => 'Stagiaire',
            'super_admin' => 'Super administrateur',
            default => $role,
        };
    }
}
