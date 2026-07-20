<?php

namespace App\Services\Platform;

use App\Enums\PersonOperationalStatus;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AuditLogService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{actor_id?: string|null, from?: string|null, to?: string|null, auditable_type?: string|null, action?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function indexData(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $entries = $this->filteredQuery($normalizedFilters)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return [
            'entries' => $this->entriesForDisplay($entries),
            'filters' => $normalizedFilters,
            'filtersActive' => $this->filtersActive($normalizedFilters),
            'journalHasEntries' => AuditLog::query()->exists(),
            'authors' => $this->authorOptions(),
            'objectTypes' => $this->distinctOptions('auditable_type'),
            'actions' => $this->distinctOptions('action'),
        ];
    }

    /**
     * @param  array{actor_id?: string|null, from?: string|null, to?: string|null, auditable_type?: string|null, action?: string|null}  $filters
     * @return array{query: Builder<AuditLog>, row_count: int, filename: string}
     */
    public function prepareExport(User $actor, array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $query = $this->filteredQuery($normalizedFilters);
        $snapshotId = (clone $query)->max('id');

        if ($snapshotId === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('id', '<=', (int) $snapshotId);
        }

        $rowCount = (clone $query)->count();
        $auditSubject = new AuditLog;

        DB::connection($auditSubject->getConnectionName())->transaction(function () use (
            $actor,
            $auditSubject,
            $normalizedFilters,
            $rowCount,
        ): void {
            $this->auditLogger->record(
                actorId: (int) $actor->getKey(),
                actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                auditable: $auditSubject,
                action: 'audit_log_exported',
                newValues: [
                    'data_nature' => "Journal d'audit",
                    'row_count' => $rowCount,
                    'filters' => array_filter(
                        $normalizedFilters,
                        static fn (mixed $value): bool => $value !== null && $value !== '',
                    ),
                ],
                reason: 'Export CSV du journal demandé par la direction.',
            );
        });

        return [
            'query' => $query,
            'row_count' => $rowCount,
            'filename' => 'journal-audit-'.CarbonImmutable::now('UTC')->format('Ymd-His').'.csv',
        ];
    }

    /** @param Builder<AuditLog> $query */
    public function writeCsv(Builder $query): int
    {
        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            throw new LogicException("Le flux d'export ne peut pas être ouvert.");
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv(
            $stream,
            ['Auteur', 'Date (heure de Niamey)', 'Objet', 'Action', 'Ancienne valeur', 'Nouvelle valeur', 'Motif'],
            separator: ';',
            enclosure: '"',
            escape: '',
        );

        foreach ($query->orderByDesc('occurred_at')->orderByDesc('id')->cursor() as $entry) {
            $display = $this->serializeEntry($entry);
            $this->writeCsvRow($stream, $display);
        }

        return fclose($stream) ? 0 : 1;
    }

    /**
     * Requête unique partagée par l'index et l'export (PERM-06).
     *
     * @param  array{actor_id: string|null, from: string|null, to: string|null, auditable_type: string|null, action: string|null}  $filters
     * @return Builder<AuditLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        $timezone = (string) config('app.display_timezone');
        $from = $filters['from'] !== null
            ? CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc()
            : null;
        $to = $filters['to'] !== null
            ? CarbonImmutable::parse($filters['to'], $timezone)->endOfDay()->utc()
            : null;

        return AuditLog::query()
            ->when($filters['actor_id'] === 'system', static fn (Builder $query): Builder => $query->whereNull('actor_id'))
            ->when(
                $filters['actor_id'] !== null && $filters['actor_id'] !== 'system',
                static fn (Builder $query): Builder => $query->where('actor_id', (int) $filters['actor_id']),
            )
            ->when($from !== null, static fn (Builder $query): Builder => $query->where('occurred_at', '>=', $from))
            ->when($to !== null, static fn (Builder $query): Builder => $query->where('occurred_at', '<=', $to))
            ->when(
                $filters['auditable_type'] !== null,
                static fn (Builder $query): Builder => $query->where('auditable_type', $filters['auditable_type']),
            )
            ->when(
                $filters['action'] !== null,
                static fn (Builder $query): Builder => $query->where('action', $filters['action']),
            );
    }

    /**
     * @param  array{actor_id?: string|null, from?: string|null, to?: string|null, auditable_type?: string|null, action?: string|null}  $filters
     * @return array{actor_id: string|null, from: string|null, to: string|null, auditable_type: string|null, action: string|null}
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'actor_id' => $this->nullableString($filters['actor_id'] ?? null),
            'from' => $this->nullableString($filters['from'] ?? null),
            'to' => $this->nullableString($filters['to'] ?? null),
            'auditable_type' => $this->nullableString($filters['auditable_type'] ?? null),
            'action' => $this->nullableString($filters['action'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, string|null> $filters */
    private function filtersActive(array $filters): bool
    {
        return collect($filters)->contains(static fn (?string $value): bool => $value !== null);
    }

    /**
     * @param  LengthAwarePaginator<int, AuditLog>  $entries
     * @return array<string, mixed>
     */
    private function entriesForDisplay(LengthAwarePaginator $entries): array
    {
        return $entries->through(fn (AuditLog $entry): array => $this->serializeEntry($entry))->toArray();
    }

    /**
     * @return array{id: int, actor: string, occurred_at: string, object: string, object_technical: string, action: string, action_technical: string, changes: list<array{field: string, label: string, old: string, new: string}>, reason: string|null}
     */
    private function serializeEntry(AuditLog $entry): array
    {
        return [
            'id' => (int) $entry->getKey(),
            'actor' => $entry->actor_label,
            'occurred_at' => DateTimeFormatter::format($entry->occurred_at),
            'object' => $this->objectLabel($entry->auditable_type, $entry->auditable_id),
            'object_technical' => $entry->auditable_type,
            'action' => $this->actionLabel($entry->action),
            'action_technical' => $entry->action,
            'changes' => $this->changes($entry->old_values, $entry->new_values),
            'reason' => $entry->reason,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @return list<array{field: string, label: string, old: string, new: string}>
     */
    private function changes(?array $oldValues, ?array $newValues): array
    {
        $oldValues = $oldValues ?? [];
        $newValues = $newValues ?? [];
        $fields = array_values(array_unique([...array_keys($oldValues), ...array_keys($newValues)]));
        $changes = [];

        foreach ($fields as $field) {
            if ($this->isSensitiveField($field)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $this->fieldLabel($field),
                'old' => $this->valueLabel($field, $oldValues[$field] ?? null),
                'new' => $this->valueLabel($field, $newValues[$field] ?? null),
            ];
        }

        return $changes;
    }

    private function isSensitiveField(string $field): bool
    {
        return preg_match('/password|mot_de_passe|token|secret|verification_code|code_verification/i', $field) === 1;
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'state' => 'État du compte',
            'operational_status' => 'Situation de la personne',
            'phone' => 'Téléphone',
            'must_change_password' => 'Changement de mot de passe requis',
            'locked_until' => "Blocage jusqu'au",
            'failed_attempts' => 'Tentatives échouées',
            'roles' => 'Rôles',
            'permissions' => 'Permissions',
            'sessions_revoked' => 'Sessions fermées',
            'data_nature' => 'Nature des données',
            'row_count' => 'Nombre de lignes',
            'filters' => 'Filtres',
            default => "{$field} (nom technique)",
        };
    }

    private function valueLabel(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'Non renseigné';
        }

        if ($field === 'state' && is_string($value)) {
            return UserState::tryFrom($value)?->label() ?? $value;
        }

        if ($field === 'operational_status' && is_string($value)) {
            return PersonOperationalStatus::tryFrom($value)?->label() ?? $value;
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_array($value)) {
            $encoded = json_encode(
                $this->withoutSensitiveValues($value),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );

            return $encoded === false ? 'Valeur illisible' : $encoded;
        }

        return (string) $value;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function withoutSensitiveValues(array $values): array
    {
        $safeValues = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveField((string) $key)) {
                continue;
            }

            $safeValues[$key] = is_array($value)
                ? $this->withoutSensitiveValues($value)
                : $value;
        }

        return $safeValues;
    }

    private function objectLabel(string $type, ?int $identifier): string
    {
        $label = match ($type) {
            User::class => 'Compte',
            Person::class => 'Personne',
            AuditLog::class => "Journal d'audit",
            default => "{$type} (nom technique)",
        };

        return $identifier === null ? $label : "{$label} #{$identifier}";
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'created', 'person_created', 'user_created' => 'Création',
            'updated', 'person_updated', 'user_updated' => 'Modification',
            'person_status_changed', 'user_state_changed' => "Changement d'état",
            'user_role_assigned' => 'Attribution de rôle',
            'user_roles_changed' => 'Modification des rôles',
            'user_role_removed' => 'Retrait de rôle',
            'password_changed' => 'Changement de mot de passe',
            'password_reset_by_administrator' => 'Réinitialisation du mot de passe',
            'login_lock_started' => 'Blocage de connexion',
            'login_lock_expired', 'login_lock_cleared_by_password_reset' => 'Fin du blocage de connexion',
            'audit_log_exported' => "Export du journal d'audit",
            default => "{$action} (nom technique)",
        };
    }

    /** @return list<array{value: string, label: string}> */
    private function authorOptions(): array
    {
        return AuditLog::query()
            ->select(['actor_id', 'actor_label'])
            ->distinct()
            ->orderBy('actor_label')
            ->get()
            ->map(static fn (AuditLog $entry): array => [
                'value' => $entry->actor_id === null ? 'system' : (string) $entry->actor_id,
                'label' => $entry->actor_label,
            ])
            ->unique('value')
            ->values()
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function distinctOptions(string $column): array
    {
        return AuditLog::query()
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): array => [
                'value' => $value,
                'label' => $column === 'action'
                    ? $this->actionLabel($value)
                    : $this->objectLabel($value, null),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  resource  $stream
     * @param  array{id: int, actor: string, occurred_at: string, object: string, object_technical: string, action: string, action_technical: string, changes: list<array{field: string, label: string, old: string, new: string}>, reason: string|null}  $entry
     */
    private function writeCsvRow(mixed $stream, array $entry): void
    {
        $old = collect($entry['changes'])
            ->map(static fn (array $change): string => "{$change['label']} : {$change['old']}")
            ->implode(' | ') ?: 'Aucune ancienne valeur';
        $new = collect($entry['changes'])
            ->map(static fn (array $change): string => "{$change['label']} : {$change['new']}")
            ->implode(' | ') ?: 'Aucune nouvelle valeur';

        fputcsv(
            $stream,
            array_map(fn (string $cell): string => $this->spreadsheetSafe($cell), [
                $entry['actor'],
                $entry['occurred_at'],
                $entry['object'],
                $entry['action'],
                $old,
                $new,
                $entry['reason'] ?? '',
            ]),
            separator: ';',
            enclosure: '"',
            escape: '',
        );
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/\A[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }
}
