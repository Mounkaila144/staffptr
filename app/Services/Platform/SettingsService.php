<?php

namespace App\Services\Platform;

use App\Models\Identity\User;
use App\Models\Platform\Setting;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

class SettingsService
{
    public const CACHE_KEY = 'settings:all';

    /** @var array<string, array{type: string, label: string, rules: list<string>, item_rules?: list<string>, unit?: string}> */
    private const DEFINITIONS = [
        'working_days' => [
            'type' => 'array',
            'label' => 'Jours travaillés',
            'rules' => ['required', 'array', 'min:1'],
            'item_rules' => ['required', 'string', 'distinct', 'in:lun,mar,mer,jeu,ven,sam,dim'],
        ],
        'report_deadline_time' => [
            'type' => 'time',
            'label' => 'Heure limite du rapport',
            'rules' => ['required', 'date_format:H:i'],
        ],
        'report_reminder_minutes' => [
            'type' => 'integer',
            'label' => 'Délai de rappel',
            'rules' => ['required', 'integer', 'min:1', 'max:1440'],
            'unit' => 'minutes',
        ],
        'intern_limit_per_tutor' => [
            'type' => 'integer',
            'label' => 'Limite de stagiaires par tuteur',
            'rules' => ['required', 'integer', 'min:1', 'max:100'],
            'unit' => 'stagiaires',
        ],
        'reserve_percentage' => [
            'type' => 'percentage',
            'label' => 'Pourcentage de réserve',
            'rules' => ['required', 'integer', 'min:0', 'max:100'],
            'unit' => '%',
        ],
        'reserve_target_months' => [
            'type' => 'integer',
            'label' => 'Objectif de réserve',
            'rules' => ['required', 'integer', 'min:1', 'max:120'],
            'unit' => 'mois',
        ],
        'attachment_allowed_types' => [
            'type' => 'array',
            'label' => 'Types de pièces jointes',
            'rules' => ['required', 'array', 'min:1'],
            'item_rules' => ['required', 'string', 'distinct', 'in:pdf,jpeg,png,webp,heic'],
        ],
        'attachment_max_size_bytes' => [
            'type' => 'integer',
            'label' => 'Taille maximale des pièces jointes',
            'rules' => ['required', 'integer', 'min:1024', 'max:52428800'],
            'unit' => 'octets',
        ],
        'login_max_failed_attempts' => [
            'type' => 'integer',
            'label' => 'Tentatives de connexion autorisées',
            'rules' => ['required', 'integer', 'min:1', 'max:100'],
            'unit' => 'tentatives',
        ],
        'login_lockout_minutes' => [
            'type' => 'integer',
            'label' => 'Durée du blocage de connexion',
            'rules' => ['required', 'integer', 'min:1', 'max:1440'],
            'unit' => 'minutes',
        ],
        'contract_end_warning_days' => [
            'type' => 'integer',
            'label' => 'Alerte de fin de contrat',
            'rules' => ['required', 'integer', 'min:0', 'max:365'],
            'unit' => 'jours',
        ],
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return list<string> */
    public static function valueRules(string $key): array
    {
        return self::definition($key)['rules'];
    }

    /** @return list<string>|null */
    public static function itemRules(string $key): ?array
    {
        return self::definition($key)['item_rules'] ?? null;
    }

    public static function type(string $key): string
    {
        return self::definition($key)['type'];
    }

    public static function label(string $key): string
    {
        return self::definition($key)['label'];
    }

    /** @return list<string> */
    public function workingDays(): array
    {
        return $this->stringList('working_days');
    }

    public function reportDeadlineTime(): string
    {
        return $this->stringValue('report_deadline_time');
    }

    public function reportReminderMinutes(): int
    {
        return $this->integerValue('report_reminder_minutes');
    }

    public function internLimitPerTutor(): int
    {
        return $this->integerValue('intern_limit_per_tutor');
    }

    public function reservePercentage(): int
    {
        return $this->integerValue('reserve_percentage');
    }

    public function reserveTargetMonths(): int
    {
        return $this->integerValue('reserve_target_months');
    }

    /** @return list<string> */
    public function attachmentAllowedTypes(): array
    {
        return $this->stringList('attachment_allowed_types');
    }

    public function attachmentMaxSizeBytes(): int
    {
        return $this->integerValue('attachment_max_size_bytes');
    }

    public function loginMaxFailedAttempts(): int
    {
        return $this->integerValue('login_max_failed_attempts');
    }

    public function loginLockoutMinutes(): int
    {
        return $this->integerValue('login_lockout_minutes');
    }

    public function contractEndWarningDays(): int
    {
        return $this->integerValue('contract_end_warning_days');
    }

    /** @return list<array{key: string, label: string, type: string, value: mixed, effective_at: string, unit: string|null, numeric: bool}> */
    public function forManagement(): array
    {
        $values = $this->allValues();
        $settings = Setting::query()->get()->keyBy('key');
        $result = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $setting = $settings->get($key);

            if (! $setting instanceof Setting) {
                throw new RuntimeException("Le paramètre requis {$key} est absent.");
            }

            $result[] = [
                'key' => $key,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'value' => $values[$key],
                'effective_at' => DateTimeFormatter::format($setting->effective_at),
                'unit' => $definition['unit'] ?? null,
                'numeric' => in_array($definition['type'], ['integer', 'percentage'], true),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return Collection<int, Setting>
     */
    public function update(array $values, CarbonImmutable $effectiveAt, User $actor): Collection
    {
        foreach ($values as $key => $value) {
            $this->validateValue($key, $value);
        }

        $subject = new Setting;
        $updated = DB::connection($subject->getConnectionName())->transaction(function () use (
            $values,
            $effectiveAt,
            $actor,
        ): Collection {
            $updated = (new Setting)->newCollection();

            foreach ($values as $key => $value) {
                $setting = Setting::query()->where('key', $key)->lockForUpdate()->firstOrFail();

                if ($setting->value === $value) {
                    continue;
                }

                $oldValues = [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'effective_at' => $setting->effective_at->format('Y-m-d H:i:s.v'),
                ];
                $setting->fill([
                    'value' => $value,
                    'type' => self::definition($key)['type'],
                    'effective_at' => $effectiveAt->utc(),
                ]);
                $newValues = [
                    'key' => $setting->key,
                    'value' => $value,
                    'effective_at' => $effectiveAt->utc()->format('Y-m-d H:i:s.v'),
                ];

                $this->auditLogger->runExplicitly(
                    auditable: $setting,
                    operation: fn (): bool => $setting->saveOrFail(),
                    actorId: $actor->getKey(),
                    actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                    action: 'setting_changed',
                    oldValues: $oldValues,
                    newValues: $newValues,
                    reason: 'Modification des paramètres généraux.',
                );

                $updated->push($setting);
            }

            return $updated;
        });

        if ($updated->isNotEmpty()) {
            Cache::forget(self::CACHE_KEY);
        }

        return $updated;
    }

    /** @return array{key: string, label: string, current_value: mixed, proposed_value: mixed, delta: int|null, consequence: string} */
    public function preview(string $key, mixed $proposedValue): array
    {
        $this->validateValue($key, $proposedValue);
        $definition = self::definition($key);
        $currentValue = $this->value($key);
        $delta = is_int($currentValue) && is_int($proposedValue)
            ? $proposedValue - $currentValue
            : null;
        $difference = $delta === null
            ? ''
            : sprintf(' Écart calculé : %s%d %s.', $delta > 0 ? '+' : '', $delta, $definition['unit'] ?? 'unités');

        return [
            'key' => $key,
            'label' => $definition['label'],
            'current_value' => $currentValue,
            'proposed_value' => $proposedValue,
            'delta' => $delta,
            'consequence' => sprintf(
                'La valeur passera de %s à %s dès la confirmation.%s',
                $this->displayValue($currentValue, $definition['unit'] ?? null),
                $this->displayValue($proposedValue, $definition['unit'] ?? null),
                $difference,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::query()
                ->get(['key', 'value'])
                ->mapWithKeys(static fn (Setting $setting): array => [$setting->key => $setting->value])
                ->all();
        });
    }

    private function value(string $key): mixed
    {
        $values = $this->allValues();

        if (! array_key_exists($key, $values)) {
            throw new RuntimeException("Le paramètre requis {$key} est absent.");
        }

        $this->validateValue($key, $values[$key]);

        return $values[$key];
    }

    private function integerValue(string $key): int
    {
        $value = $this->value($key);

        if (! is_int($value)) {
            throw new RuntimeException("Le paramètre {$key} doit être un entier.");
        }

        return $value;
    }

    private function stringValue(string $key): string
    {
        $value = $this->value($key);

        if (! is_string($value)) {
            throw new RuntimeException("Le paramètre {$key} doit être une chaîne.");
        }

        return $value;
    }

    /** @return list<string> */
    private function stringList(string $key): array
    {
        $value = $this->value($key);

        if (! is_array($value)) {
            throw new RuntimeException("Le paramètre {$key} doit être une liste.");
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    private function validateValue(string $key, mixed $value): void
    {
        $rules = self::valueRules($key);
        $validationRules = ['value' => $rules];
        $itemRules = self::itemRules($key);

        if ($itemRules !== null) {
            $validationRules['value.*'] = $itemRules;
        }

        Validator::make(['value' => $value], $validationRules)->validate();
    }

    /** @return array{type: string, label: string, rules: list<string>, item_rules?: list<string>, unit?: string} */
    private static function definition(string $key): array
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new InvalidArgumentException("Le paramètre {$key} n'est pas reconnu.");
        }

        return self::DEFINITIONS[$key];
    }

    private function displayValue(mixed $value, ?string $unit): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        return trim((string) $value.' '.($unit ?? ''));
    }
}
