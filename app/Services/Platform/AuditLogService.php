<?php

namespace App\Services\Platform;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use App\Enums\DocumentType;
use App\Enums\PersonOperationalStatus;
use App\Enums\RelationType;
use App\Enums\UserState;
use App\Models\Finance\Account;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Invoice;
use App\Models\Finance\MonthClosure;
use App\Models\Finance\MonthlyBudget;
use App\Models\Finance\MonthlyReport;
use App\Models\Finance\Payment;
use App\Models\Finance\Reconciliation;
use App\Models\Finance\ReserveMovement;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\Absence;
use App\Models\Identity\Company;
use App\Models\Identity\Department;
use App\Models\Identity\JobFunction;
use App\Models\Identity\Person;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\AuditLog;
use App\Models\Platform\Holiday;
use App\Models\Platform\InternalDocument;
use App\Models\Platform\InternalDocumentAcknowledgement;
use App\Models\Platform\InternalDocumentVersion;
use App\Models\Platform\Setting;
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
            'name' => 'Nom',
            'state' => 'État du compte',
            'operational_status' => 'Situation de la personne',
            'phone' => 'Téléphone',
            'department_id' => 'Service',
            'job_function_id' => 'Fonction',
            'manager_id' => 'Responsable direct',
            'relation_type' => 'Type de relation',
            'contract_start_date' => 'Date de début',
            'contract_end_date' => 'Date de fin',
            'photo_path' => 'Photo',
            'email' => 'Adresse e-mail',
            'address' => 'Adresse',
            'logo_path' => 'Logo',
            'is_active' => 'Actif',
            'label' => 'Libellé',
            'date' => "Date d'effet",
            'must_change_password' => 'Changement de mot de passe requis',
            'locked_until' => "Blocage jusqu'au",
            'failed_attempts' => 'Tentatives échouées',
            'roles' => 'Rôles',
            'permissions' => 'Permissions',
            'sessions_revoked' => 'Sessions fermées',
            'data_nature' => 'Nature des données',
            'row_count' => 'Nombre de lignes',
            'filters' => 'Filtres',
            'key' => 'Paramètre',
            'value' => 'Valeur',
            'effective_at' => "Date d'effet",
            'original_name' => "Nom d'origine",
            'mime_type' => 'Type du fichier',
            'extension' => 'Extension normalisée',
            'size_bytes' => 'Taille en octets',
            'attachable_type' => "Type d'objet rattaché",
            'attachable_id' => 'Objet rattaché',
            'uploaded_by' => 'Déposé par',
            'person_id' => 'Personne concernée',
            'document_type' => 'Type de document',
            'attachment_ulid' => 'Fichier rattaché',
            'archive_reason' => "Motif d'archivage",
            'archived_at' => "Date d'archivage",
            'title' => 'Titre',
            'requires_acknowledgement' => "Accusé d'acceptation requis",
            'current_version_id' => 'Version courante',
            'internal_document_id' => 'Document interne',
            'internal_document_version_id' => 'Version du document interne',
            'version_number' => 'Numéro de version',
            'effective_date' => "Date d'application",
            'published_by' => 'Publié par',
            'published_at' => 'Date de publication',
            'user_id' => 'Utilisateur concerné',
            'acknowledged_at' => "Date de l'acceptation",
            'type' => "Type d'absence",
            'start_date' => 'Début de l’absence',
            'end_date' => 'Fin de l’absence',
            'reason' => 'Motif',
            'decision_reason' => 'Motif de la décision',
            'decided_by' => 'Décision prise par',
            'decided_at' => 'Date de la décision',
            'is_essential' => 'Dépense essentielle',
            'requested_amount' => 'Montant demandé',
            'monthly_amount' => 'Montant mensuel',
            'budget_amount' => 'Montant budgété',
            'movement_amount' => 'Montant du mouvement',
            'received_amount' => 'Montant encaissé',
            'expected_total_amount' => 'Montant total attendu',
            'forecast_profit_amount' => 'Bénéfice prévisionnel',
            'physical_balance_amount' => 'Solde physique constaté',
            'calculated_balance_amount' => 'Solde calculé',
            'difference_amount' => 'Montant de l’écart',
            'difference_explanation' => 'Explication de l’écart',
            'corrective_action' => 'Action corrective',
            'reconstitution_plan' => 'Plan de reconstitution',
            'approval_state' => 'État des approbations',
            'prepared_by' => 'Préparé par',
            'controlled_by' => 'Contrôlé par',
            'validated_by' => 'Validé par',
            'closed_by' => 'Clôturé par',
            'reopened_by' => 'Rouvert par',
            'reopen_reason' => 'Motif de réouverture',
            'approver_id' => 'Compte de direction ayant décidé',
            'approval_decision' => "Décision d'approbation",
            default => "{$field} (nom technique)",
        };
    }

    private function valueLabel(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'Non renseigné';
        }

        if ($field === 'state' && is_string($value)) {
            return AbsenceState::tryFrom($value)?->label()
                ?? UserState::tryFrom($value)?->label()
                ?? $value;
        }

        if ($field === 'type' && is_string($value)) {
            return AbsenceType::tryFrom($value)?->label() ?? $value;
        }

        if ($field === 'operational_status' && is_string($value)) {
            return PersonOperationalStatus::tryFrom($value)?->label() ?? $value;
        }

        if ($field === 'relation_type' && is_string($value)) {
            return RelationType::tryFrom($value)?->label() ?? $value;
        }

        if ($field === 'document_type' && is_string($value)) {
            return DocumentType::tryFrom($value)?->label() ?? $value;
        }

        if ($field === 'is_active' && (is_bool($value) || is_int($value))) {
            return (bool) $value ? 'Oui' : 'Non';
        }

        if ($field === 'key' && is_string($value) && in_array($value, SettingsService::keys(), true)) {
            return SettingsService::label($value);
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
            Company::class => 'Entreprise',
            Department::class => 'Service',
            JobFunction::class => 'Fonction',
            User::class => 'Compte',
            Person::class => 'Personne',
            PersonDocument::class => 'Document du dossier personnel',
            InternalDocument::class => 'Document interne',
            InternalDocumentVersion::class => 'Version de document interne',
            InternalDocumentAcknowledgement::class => "Accusé d'acceptation",
            AuditLog::class => "Journal d'audit",
            Setting::class => 'Paramètre général',
            Attachment::class => 'Pièce jointe',
            Holiday::class => 'Jour férié',
            Absence::class => 'Absence',
            ExpenseCategory::class => 'Catégorie de dépense',
            Expense::class => 'Dépense',
            Account::class => 'Compte financier',
            FixedCharge::class => 'Charge fixe',
            Client::class => 'Client',
            Contract::class => 'Contrat',
            Invoice::class => 'Facture',
            Payment::class => 'Encaissement',
            ShareEntitlement::class => 'Droit à part',
            ReserveMovement::class => 'Mouvement de réserve',
            MonthlyBudget::class => 'Budget mensuel',
            Reconciliation::class => 'Rapprochement',
            MonthlyReport::class => 'Rapport financier mensuel',
            MonthClosure::class => 'Clôture mensuelle',
            default => "{$type} (nom technique)",
        };

        return $identifier === null ? $label : "{$label} #{$identifier}";
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'person_profile_updated' => 'Modification de la personne',
            'user_profile_updated' => 'Modification de la fiche',
            'manager_changed' => 'Changement de responsable',
            'company_updated' => "Modification de l'entreprise",
            'department_created' => 'Création du service',
            'department_renamed' => 'Renommage du service',
            'department_deactivated' => 'Désactivation du service',
            'department_reactivated' => 'Réactivation du service',
            'job_function_created' => 'Création de la fonction',
            'job_function_renamed' => 'Renommage de la fonction',
            'job_function_deactivated' => 'Désactivation de la fonction',
            'job_function_reactivated' => 'Réactivation de la fonction',
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
            'setting_changed' => 'Modification du paramètre',
            'attachment_uploaded' => 'Dépôt de la pièce jointe',
            'attachment_attached' => 'Rattachement de la pièce jointe',
            'person_document_deposited' => 'Dépôt du document personnel',
            'person_document_viewed' => 'Consultation du document personnel',
            'person_document_archived' => 'Archivage du document personnel',
            'internal_document_published' => 'Publication du document interne',
            'internal_document_version_published' => "Publication d'une nouvelle version",
            'internal_document_acknowledged' => 'Acceptation du document interne',
            'holiday_created' => "Création d'un jour férié",
            'holiday_updated' => "Modification d'un jour férié",
            'holiday_deactivated' => "Désactivation d'un jour férié",
            'holiday_reactivated' => "Réactivation d'un jour férié",
            'absence_requested' => "Déclaration d'une absence",
            'absence_approved' => "Approbation d'une absence",
            'absence_refused' => "Refus d'une absence",
            'absence_cancelled' => "Annulation d'une absence",
            'expense_category_created' => "Création d'une catégorie de dépense",
            'expense_category_renamed' => "Renommage d'une catégorie de dépense",
            'expense_category_deactivated' => "Désactivation d'une catégorie de dépense",
            'expense_category_reactivated' => "Réactivation d'une catégorie de dépense",
            'expense_category_essential_changed' => "Modification du marqueur essentiel d'une catégorie de dépense",
            'expense_requested' => 'Demande de dépense',
            'expense_updated' => 'Modification de la demande de dépense',
            'expense_cancelled' => 'Annulation de la demande de dépense',
            'expense_approved' => 'Approbation de la dépense',
            'expense_refused' => 'Refus de la dépense',
            'financial_account_created' => 'Création du compte financier',
            'financial_account_deactivated' => 'Désactivation du compte financier',
            'fixed_charge_created' => 'Création de la charge fixe',
            'fixed_charge_updated' => 'Modification de la charge fixe',
            'fixed_charge_activated' => 'Réactivation de la charge fixe',
            'fixed_charge_deactivated' => 'Désactivation de la charge fixe',
            'client_created' => 'Création du client',
            'client_updated' => 'Modification du client',
            'contract_created' => 'Création du contrat',
            'contract_updated' => 'Modification du contrat',
            'contract_closed' => 'Clôture du contrat',
            'invoice_created' => 'Création de la facture',
            'invoice_cancelled' => 'Annulation de la facture',
            'payment_recorded' => 'Enregistrement de l’encaissement',
            'payment_corrected' => 'Correction de l’encaissement',
            'payment_cancelled_by_counter_entry' => 'Annulation de l’encaissement par contre-écriture',
            'expense_paid' => 'Paiement de la dépense',
            'expense_payment_cancelled_by_counter_entry' => 'Annulation du paiement par contre-écriture',
            'share_payment_requested' => 'Demande de versement de part',
            'monthly_budget_created' => 'Création du budget mensuel',
            'monthly_budget_updated' => 'Modification du budget mensuel',
            'reserve_usage_requested' => 'Demande d’utilisation de la réserve',
            'reserve_usage_first_approved' => 'Première approbation de la réserve',
            'reserve_usage_approved' => 'Deuxième approbation et utilisation de la réserve',
            'reconciliation_prepared' => 'Préparation du rapprochement',
            'reconciliation_corrected' => 'Correction versionnée du rapprochement',
            'reconciliation_validated' => 'Validation du rapprochement',
            'monthly_report_prepared' => 'Préparation du rapport financier',
            'monthly_report_controlled' => 'Contrôle du rapport financier',
            'monthly_report_validated' => 'Validation du rapport financier',
            'month_closed' => 'Clôture du mois',
            'month_reopened' => 'Réouverture du mois',
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
