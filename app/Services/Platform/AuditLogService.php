<?php

namespace App\Services\Platform;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use App\Enums\DocumentType;
use App\Enums\PersonOperationalStatus;
use App\Enums\RelationType;
use App\Enums\UserState;
use App\Models\Accountability\AttendanceRecord;
use App\Models\Accountability\Blocker;
use App\Models\Accountability\DailyReport;
use App\Models\Accountability\DailyReportComment;
use App\Models\Accountability\DailyReportDecision;
use App\Models\Accountability\DailyReportVersion;
use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\ImprovementPlanAction;
use App\Models\Accountability\Internship;
use App\Models\Accountability\InternshipChecklistItem;
use App\Models\Accountability\InternshipEvaluation;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Accountability\InternshipIntakeOutcome;
use App\Models\Accountability\InternshipPlan;
use App\Models\Accountability\TaskRequest;
use App\Models\Accountability\TutorSupportSlot;
use App\Models\Accountability\WeeklyReview;
use App\Models\Accountability\WeeklyReviewObjective;
use App\Models\Finance\Account;
use App\Models\Finance\AccountMovement;
use App\Models\Finance\CapitalContribution;
use App\Models\Finance\Client;
use App\Models\Finance\Contract;
use App\Models\Finance\ContractExecutor;
use App\Models\Finance\ContributionShare;
use App\Models\Finance\CorrectionPlan;
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
use App\Models\Work\CompanyPriority;
use App\Models\Work\Deliverable;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Models\Work\Task;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AuditLogService
{
    /** Libellés des objets apparus après le premier lot (rapports, stages, travail, finances avancées). */
    private const OBJECT_LABELS = [
        AttendanceRecord::class => 'Pointage de présence',
        Blocker::class => 'Blocage',
        DailyReport::class => 'Rapport du jour',
        DailyReportComment::class => 'Commentaire de rapport du jour',
        DailyReportDecision::class => 'Décision sur un rapport du jour',
        DailyReportVersion::class => 'Version de rapport du jour',
        ImprovementPlan::class => 'Plan d\'amélioration',
        ImprovementPlanAction::class => 'Action du plan d\'amélioration',
        Internship::class => 'Stage',
        InternshipChecklistItem::class => 'Étape de la liste de stage',
        InternshipEvaluation::class => 'Évaluation de stage',
        InternshipIntakeForm::class => 'Fiche d\'entrée de stagiaire',
        InternshipIntakeOutcome::class => 'Décision sur une fiche d\'entrée',
        InternshipPlan::class => 'Plan de stage',
        TaskRequest::class => 'Demande de tâche',
        TutorSupportSlot::class => 'Créneau de suivi du tuteur',
        WeeklyReview::class => 'Revue hebdomadaire',
        WeeklyReviewObjective::class => 'Objectif de revue hebdomadaire',
        AccountMovement::class => 'Mouvement de compte financier',
        CapitalContribution::class => 'Apport en capital',
        ContractExecutor::class => 'Exécutant du contrat',
        ContributionShare::class => 'Part de contribution',
        CorrectionPlan::class => 'Plan correctif',
        CompanyPriority::class => 'Priorité de l\'entreprise',
        Deliverable::class => 'Livrable',
        Objective::class => 'Objectif',
        Project::class => 'Projet',
        Task::class => 'Tâche',
    ];

    /** Libellés des actions apparues après le premier lot. */
    private const ACTION_LABELS = [
        'attendance_arrival_recorded' => 'Pointage d\'arrivée',
        'attendance_departure_recorded' => 'Pointage de départ',
        'blocker_created' => 'Signalement d\'un blocage',
        'blocker_transitioned' => 'Changement d\'état du blocage',
        'capital_contribution_approved' => 'Approbation de l\'apport en capital',
        'capital_contribution_recorded' => 'Enregistrement de l\'apport en capital',
        'capital_contribution_refused' => 'Refus de l\'apport en capital',
        'company_priority_created' => 'Création de la priorité',
        'company_priority_updated' => 'Modification de la priorité',
        'contribution_shares_issued_from_capital' => 'Émission de parts sur un apport en capital',
        'contribution_shares_issued_from_payment' => 'Émission de parts sur un encaissement',
        'correction_plan_created' => 'Création du plan correctif',
        'correction_plan_revised' => 'Révision du plan correctif',
        'correction_plan_validated' => 'Validation du plan correctif',
        'daily_report_corrected' => 'Correction du rapport du jour',
        'daily_report_returned' => 'Retour du rapport du jour pour correction',
        'daily_report_submitted' => 'Envoi du rapport du jour',
        'daily_report_validated' => 'Validation du rapport du jour',
        'deliverable_created' => 'Création du livrable',
        'deliverable_status_changed' => 'Changement d\'état du livrable',
        'improvement_plan_closed' => 'Clôture du plan d\'amélioration',
        'improvement_plan_created' => 'Création du plan d\'amélioration',
        'intern_access_closed' => 'Fermeture de l\'accès du stagiaire',
        'internship_activated' => 'Activation du stage',
        'internship_checklist_item_completed' => 'Étape de stage accomplie',
        'internship_ended' => 'Fin du stage',
        'internship_evaluation_recorded' => 'Enregistrement d\'une évaluation de stage',
        'internship_evaluation_validated' => 'Validation d\'une évaluation de stage',
        'internship_final_evaluation_recorded' => 'Enregistrement de l\'évaluation finale du stage',
        'internship_intake_approved' => 'Approbation de la fiche d\'entrée',
        'internship_intake_drafted' => 'Rédaction de la fiche d\'entrée',
        'internship_intake_refused' => 'Refus de la fiche d\'entrée',
        'internship_intake_submitted' => 'Envoi de la fiche d\'entrée',
        'internship_plan_created' => 'Création du plan de stage',
        'objective_commented' => 'Commentaire sur l\'objectif',
        'objective_proposed' => 'Proposition d\'objectif',
        'objective_state_changed' => 'Changement d\'état de l\'objectif',
        'objective_updated' => 'Modification de l\'objectif',
        'objective_validated' => 'Validation de l\'objectif',
        'project_commented' => 'Commentaire sur le projet',
        'project_created' => 'Création du projet',
        'project_link_added' => 'Ajout d\'un lien au projet',
        'project_member_added' => 'Ajout d\'un membre au projet',
        'project_status_changed' => 'Changement d\'état du projet',
        'task_request_created' => 'Demande de tâche',
        'task_request_processed' => 'Traitement de la demande de tâche',
        'weekly_review_commented' => 'Commentaire sur la revue hebdomadaire',
        'weekly_review_objective_recorded' => 'Enregistrement d\'un objectif de revue',
        'weekly_review_opened' => 'Ouverture de la revue hebdomadaire',
        'weekly_review_submitted' => 'Envoi de la revue hebdomadaire',
        'weekly_review_validated_by_reviewee' => 'Validation de la revue par la personne évaluée',
        'weekly_review_validated_by_reviewer' => 'Validation de la revue par le relecteur',
        'work_task_commented' => 'Commentaire sur la tâche',
        'work_task_created' => 'Création de la tâche',
        'work_task_link_added' => 'Ajout d\'un lien à la tâche',
        'work_task_status_changed' => 'Changement d\'état de la tâche',
    ];

    /** Libellés des champs apparus après le premier lot. */
    private const FIELD_LABELS = [
        'full_name' => 'Nom complet',
        'first_seen_at' => 'Première date connue',
        'updated_at' => 'Date de modification',
        'created_at' => 'Date de création',
        'id' => 'Identifiant',
        'contributor_id' => 'Apporteur',
        'contribution_amount' => 'Montant de l\'apport',
        'declared_on' => 'Date de déclaration',
        'purpose' => 'Objet',
        'idempotency_key' => 'Clé anti-doublon',
        'capital_contribution_id' => 'Apport en capital',
        'holder_id' => 'Détenteur',
        'origin' => 'Origine',
        'entry_type' => 'Type d\'écriture',
        'base_amount' => 'Montant de base',
        'vintage_year' => 'Année d\'émission',
        'coefficient_basis_points' => 'Coefficient (points de base)',
        'issued_shares' => 'Parts émises',
        'occurred_on' => 'Date',
        'created_by' => 'Créé par',
        'approved_by' => 'Approuvé par',
        'approved_at' => 'Date d\'approbation',
        'refused_by' => 'Refusé par',
        'refused_at' => 'Date du refus',
        'refusal_reason' => 'Motif du refus',
        'project_id' => 'Projet',
        'objective_id' => 'Objectif',
        'parent_id' => 'Élément parent',
        'assignee_id' => 'Responsable',
        'due_date' => 'Échéance',
        'priority' => 'Priorité',
        'status' => 'État',
        'requester_id' => 'Demandeur',
        'category_id' => 'Catégorie',
        'beneficiary' => 'Bénéficiaire',
        'expected_result' => 'Résultat attendu',
        'project_or_contract_note' => 'Projet ou contrat concerné',
        'candidate_user_id' => 'Candidat',
        'tutor_id' => 'Tuteur',
        'real_need' => 'Besoin réel',
        'mission' => 'Mission',
        'duration_weeks' => 'Durée en semaines',
        'tools' => 'Outils',
        'internship_intake_form_id' => 'Fiche d\'entrée',
        'position' => 'Position',
        'description' => 'Description',
        'submitted_at' => 'Date d\'envoi',
        'company_priority_id' => 'Priorité de l\'entreprise',
        'indicator' => 'Indicateur',
        'target_value' => 'Valeur cible',
        'expected_evidence' => 'Preuve attendue',
        'required_means' => 'Moyens nécessaires',
        'progress' => 'Avancement',
        'internship_id' => 'Stage',
        'checklist_type' => 'Type de liste',
        'author_id' => 'Auteur',
        'report_date' => 'Date du rapport',
        'daily_report_id' => 'Rapport du jour',
        'planned_task' => 'Tâche prévue',
        'achieved_result' => 'Résultat obtenu',
        'evidence_link' => 'Lien vers la preuve',
        'blocker_present' => 'Blocage signalé',
        'blocker_details' => 'Détail du blocage',
        'next_action' => 'Prochaine action',
        'help_requested' => 'Aide demandée',
        'help_details' => 'Détail de l\'aide demandée',
        'correction_reason' => 'Motif de la correction',
        'lateness_explanation' => 'Explication du retard',
        'work_date' => 'Jour de travail',
        'arrived_at' => 'Heure d\'arrivée',
        'late_minutes' => 'Minutes de retard',
        'opening_balance_amount' => 'Solde d\'ouverture',
        'opening_balance_date' => 'Date du solde d\'ouverture',
        'deactivation_reason' => 'Motif de désactivation',
        'deactivated_at' => 'Date de désactivation',
        'contact' => 'Contact',
        'notes' => 'Notes',
        'client_id' => 'Client',
        'reference' => 'Référence',
        'has_execution' => 'Comporte une exécution',
        'starts_on' => 'Date de début',
        'ends_on' => 'Date de fin',
        'executor_ids' => 'Exécutants',
        'contract_id' => 'Contrat',
        'number' => 'Numéro',
        'total_amount' => 'Montant total',
        'issued_on' => 'Date d\'émission',
        'due_on' => 'Échéance',
        'cancellation_reason' => 'Motif d\'annulation',
        'cancelled_at' => 'Date d\'annulation',
        'account_id' => 'Compte financier',
        'direction' => 'Sens du mouvement',
        'effective_on' => 'Date d\'effet',
        'source_type' => 'Type d\'origine',
        'source_id' => 'Origine',
        'reversal_of_id' => 'Contre-écriture de',
        'validated_at' => 'Date de validation',
        'payment_id' => 'Encaissement',
        'beneficiary_id' => 'Bénéficiaire',
        'beneficiary_key' => 'Bénéficiaire (clé)',
        'share_type' => 'Type de part',
        'rate_basis_points' => 'Taux (points de base)',
        'rate_divisor' => 'Diviseur du taux',
        'share_amount' => 'Montant de la part',
        'paid_amount' => 'Montant versé',
        'calculation_method' => 'Méthode de calcul',
        'period_start' => 'Début de période',
        'period_end' => 'Fin de période',
        'source_received_on' => 'Date d\'encaissement d\'origine',
        'receipt_sequence_id' => 'Séquence de reçu',
        'receipt_number' => 'Numéro de reçu',
        'invoice_id' => 'Facture',
        'received_on' => 'Date d\'encaissement',
        'received_at' => 'Heure d\'encaissement',
        'payment_mode' => 'Mode de paiement',
        'correction_of_id' => 'Correction de',
        'late_recording' => 'Saisie tardive',
        'post_reopening' => 'Après réouverture du mois',
        'reversed_at' => 'Date de contre-écriture',
        'reversal_payment_id' => 'Encaissement de contre-écriture',
        'month' => 'Mois',
        'owner_id' => 'Responsable',
        'target' => 'Cible',
        'origin_type' => 'Type d\'origine',
        'origin_id' => 'Origine',
        'solicited_user_id' => 'Personne sollicitée',
        'problem' => 'Problème',
        'urgency' => 'Urgence',
        'reported_on' => 'Date du signalement',
        'deadline_impact' => 'Impact sur l\'échéance',
        'attempted_action' => 'Action déjà tentée',
        'comment_id' => 'Commentaire',
        'body' => 'Texte',
        'login_attempt_id' => 'Tentative de connexion',
        'reviewer_id' => 'Relecteur',
        'decision' => 'Décision',
        'difference_direction' => 'Sens de l\'écart',
        'responsible_id' => 'Responsable',
        'previous_id' => 'Version précédente',
        'skills_to_learn' => 'Compétences à acquérir',
        'objectives' => 'Objectifs',
        'weekly_tasks' => 'Tâches hebdomadaires',
        'evaluator_id' => 'Évaluateur',
        'week_start_date' => 'Début de semaine',
        'observed_progress' => 'Progrès constatés',
        'evidence' => 'Preuves',
        'next_steps' => 'Prochaines étapes',
        'cancel_reason' => 'Motif d\'annulation',
        'paid_on' => 'Date de paiement',
        'payment_reference' => 'Référence du paiement',
        'paid_by' => 'Payé par',
        'paid_at' => 'Date du paiement',
        'beneficiary_user_id' => 'Bénéficiaire',
        'share_entitlement_id' => 'Droit à part',
        'payment_attachment_id' => 'Justificatif de paiement',
        'original_attachment_id' => 'Pièce jointe d’origine',
        'is_advance_reimbursement' => 'Remboursement d\'avance',
        'counter_entry_of_id' => 'Contre-écriture de',
        'payment_idempotency_key' => 'Clé anti-doublon du paiement',
        'payment_cancellation_reason' => 'Motif d\'annulation du paiement',
        'certificate_conditions_met' => 'Conditions de l\'attestation remplies',
        'link_id' => 'Lien',
        'url' => 'Adresse du lien',
        'version' => 'Version',
        'lines' => 'Lignes',
        'completed_by' => 'Accompli par',
        'completed_at' => 'Date d\'accomplissement',
        'ended_at' => 'Date de fin',
        'controlled_at' => 'Date du contrôle',
        'alert_level' => 'Niveau d\'alerte',
        'alert_source_date' => 'Date de référence de l\'alerte',
        'monthly_report_id' => 'Rapport financier mensuel',
        'closed_at' => 'Date de clôture',
        'frozen_alert_level' => 'Niveau d\'alerte figé',
        'resolved_at' => 'Date de résolution',
        'closure_reason' => 'Motif de clôture',
        'reopened_at' => 'Date de réouverture',
        'requested_by' => 'Demandé par',
        'is_urgent' => 'Urgent',
        'client_name' => 'Nom du client',
        'planned_budget_xof' => 'Budget prévu (F CFA)',
        'spent_budget_xof' => 'Budget consommé (F CFA)',
        'joined_on' => 'Date d\'arrivée',
        'treated_by' => 'Traité par',
        'treated_at' => 'Date de traitement',
        'finding' => 'Constat',
        'actions' => 'Actions',
        'responsibles' => 'Responsables',
        'plan_id' => 'Plan',
        'revision_reason' => 'Motif de la révision',
        'subject_user_id' => 'Personne concernée',
        'scheduled_on' => 'Date prévue',
        'weekly_review_id' => 'Revue hebdomadaire',
        'result' => 'Résultat',
        'gap_cause' => 'Cause de l\'écart',
        'reviewer_comment' => 'Commentaire du relecteur',
        'requested_at' => 'Date de la demande',
        'reviewee_comment' => 'Commentaire de la personne évaluée',
        'reviewee_validated_by' => 'Validé par la personne évaluée',
        'reviewee_validated_at' => 'Date de validation par la personne évaluée',
        'left_at' => 'Heure de départ',
        'reviewer_validated_by' => 'Validé par le relecteur',
        'reviewer_validated_at' => 'Date de validation par le relecteur',
        'support_provided' => 'Soutien apporté',
        'improvement_plan_id' => 'Plan d\'amélioration',
        'expense_id' => 'Dépense',
        'first_approved_by' => 'Première approbation par',
        'second_approved_by' => 'Deuxième approbation par',
        'weekday' => 'Jour de la semaine',
        'start_time' => 'Heure de début',
        'planned_date' => 'Date prévue',
        'actual_date' => 'Date réelle',
        'target_label' => 'Compte concerné',
        'identity_verification' => 'Vérification d\'identité',
        'observed_result' => 'Résultat observé',
    ];

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
            default => self::FIELD_LABELS[$field] ?? "{$field} (nom technique)",
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

        // Une date calendaire seule s'affiche au format du pays, jamais en ISO brut.
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            return $date instanceof CarbonImmutable ? $date->format('d/m/Y') : $value;
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
            default => self::OBJECT_LABELS[$type] ?? "{$type} (nom technique)",
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
            'password_reset_without_whatsapp' => 'Réinitialisation du mot de passe sans code WhatsApp',
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
            'expense_payment_proof_attached' => 'Ajout du justificatif de paiement',
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
            default => self::ACTION_LABELS[$action] ?? "{$action} (nom technique)",
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
