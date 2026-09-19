<?php

namespace App\Services\Platform;

use App\Enums\AbsenceState;
use App\Enums\DailyReportState;
use App\Enums\ObjectiveState;
use App\Enums\ProjectStatus;
use App\Enums\UserState;
use App\Models\Accountability\DailyReport;
use App\Models\Finance\Account;
use App\Models\Identity\Absence;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use App\Services\Accountability\TutorCapacityService;
use App\Services\Finance\AlertLevelService;
use App\Services\Finance\FinancialDashboardService;
use App\Services\Finance\ReserveService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Composition du tableau de bord direction (AC 25 à 31, AC 42, AC 43, FR167, FR170, P5).
 *
 * **Pourquoi ici.** L'écran croise `Identity`, `Work`, `Accountability` et `Finance`. Aucun de ces
 * modules ne peut légitimement le posséder sans lire les modèles des autres. La composition vit
 * donc dans `Platform`, en **lecture seule** : ce service n'écrit dans aucun modèle, il appelle les
 * services propriétaires ou agrège. La règle de couplage de `source-tree.md` est ainsi respectée
 * dans son intention comme dans sa lettre.
 *
 * **Cloisonnement.** Comme pour le tableau de bord financier, un bloc non autorisé est absent de
 * la charge utile, pas rendu vide (AC 23, AC 43, FR172).
 *
 * **Coût.** C'est l'écran le plus dense du produit et le principal risque N+1 (architecture
 * § 18.3). Chaque bloc est une agrégation, jamais une boucle de requêtes : le nombre de requêtes ne
 * dépend pas du nombre de comptes, de projets ou de stagiaires. {@see MAX_QUERIES} en fixe le
 * plafond opposable, vérifié par test (AC 31).
 */
final readonly class DirectionDashboardService
{
    private const TIMEZONE = 'Africa/Niamey';

    private const TTL_SECONDS = 300;

    /**
     * Plafond de requêtes pour un rendu complet, tous blocs autorisés et caches froids.
     *
     * Ce n'est pas une mesure constatée mais un contrat : le test qui l'applique échoue dès qu'un
     * bloc introduit une boucle de requêtes. Le relever demande de justifier pourquoi (AC 31).
     */
    public const MAX_QUERIES = 60;

    public function __construct(
        private TutorCapacityService $tutorCapacity,
        private ReserveService $reserve,
        private AlertLevelService $alertLevelService,
        private FinancialDashboardService $financialDashboard,
    ) {}

    /**
     * Blocs autorisés, **dans l'ordre d'affichage**. « En attente de mon approbation » vient en
     * premier et le reste ne peut pas le déplacer (AC 26, FR167) : l'ordre des clés du tableau est
     * l'ordre de rendu, et la page les parcourt sans les retrier.
     *
     * @return array<string, array<string, mixed>>
     */
    public function blocks(User $viewer): array
    {
        $blocks = [];

        foreach ($this->definitions($viewer) as $key => $definition) {
            if (! $viewer->can($definition['permission'])) {
                continue;
            }

            $blocks[$key] = $this->cached($viewer, $key, $definition['build']);
        }

        return $blocks;
    }

    /**
     * Cache par utilisateur et par bloc, plafonné à cinq minutes (architecture § 19.1).
     *
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function cached(User $viewer, string $block, Closure $build): array
    {
        return Cache::remember(
            "dash:{$viewer->getKey()}:direction:{$block}",
            self::TTL_SECONDS,
            $build,
        );
    }

    /**
     * Niveau d'alerte affiché en permanence, libellé compris (AC 4, AC 6, NFR31). Il n'est pas mis
     * en cache ici : {@see AlertLevelService} porte déjà son propre cache et son invalidation.
     *
     * @return array<string, mixed>
     */
    public function alert(): array
    {
        return $this->alertLevelService->assess()->toArray();
    }

    /**
     * @return array<string, array{permission: string, build: Closure(): array<string, mixed>}>
     */
    public function definitions(User $viewer): array
    {
        return [
            // Première position, sans exception (AC 26).
            'approval_queue' => [
                'permission' => 'depense.approuver',
                'build' => fn (): array => $this->financialDashboard->pendingExpenses(),
            ],
            'members_without_objective' => [
                'permission' => 'objectif_individuel.consulter',
                'build' => fn (): array => $this->membersWithoutObjective(),
            ],
            'daily_reports' => [
                'permission' => 'rapport_quotidien.consulter',
                'build' => fn (): array => $this->dailyReports(),
            ],
            'objectives_by_state' => [
                'permission' => 'objectif_individuel.consulter',
                'build' => fn (): array => $this->objectivesByState(),
            ],
            'late_projects' => [
                'permission' => 'projet.consulter',
                'build' => fn (): array => $this->lateProjects(),
            ],
            'interns_by_tutor' => [
                'permission' => 'stagiaire.consulter',
                'build' => fn (): array => $this->internsByTutor(),
            ],
            'month_collections' => [
                'permission' => 'encaissement.consulter',
                'build' => fn (): array => $this->financialDashboard->monthCollections(),
            ],
            'month_charges' => [
                'permission' => 'depense.consulter',
                'build' => fn (): array => $this->monthCharges(),
            ],
            'available_balance' => [
                'permission' => 'compte_financier.consulter',
                'build' => fn (): array => $this->availableBalance(),
            ],
            'receivables' => [
                'permission' => 'facture.consulter',
                'build' => fn (): array => $this->financialDashboard->overdueReceivables(),
            ],
            'reserve' => [
                'permission' => 'reserve.consulter',
                'build' => fn (): array => $this->reserveBlock(),
            ],
        ];
    }

    /**
     * Membres sans objectif du mois. **Les comptes `direction` y figurent comme les autres**
     * (AC 28, P5) : la direction se soumet à la même exigence que les équipes, sinon le tableau
     * mesure les autres et pas soi.
     *
     * @return array<string, mixed>
     */
    public function membersWithoutObjective(): array
    {
        $month = CarbonImmutable::now(self::TIMEZONE)->startOfMonth();
        $countedStates = array_values(array_map(
            static fn (ObjectiveState $state): string => $state->value,
            array_filter(
                ObjectiveState::cases(),
                static fn (ObjectiveState $state): bool => $state->countsTowardMonthlyLimit(),
            ),
        ));

        $members = User::query()
            ->where('state', UserState::Actif)
            ->whereDoesntHave('objectives', function ($objective) use ($month, $countedStates): void {
                $objective
                    ->whereIn('state', $countedStates)
                    ->whereBetween('due_date', [
                        $month->toDateString(),
                        $month->endOfMonth()->toDateString(),
                    ]);
            })
            ->with('person:id,full_name')
            ->orderBy('id')
            ->get(['id', 'person_id']);

        return [
            'title' => 'Membres sans objectif ce mois',
            'count' => $members->count(),
            'items' => $members
                ->map(static fn (User $member): array => [
                    'id' => (int) $member->getKey(),
                    'name' => $member->person->full_name,
                ])
                ->values()
                ->all(),
            'url' => route('objectives.index', absolute: false),
            'note' => 'Les comptes de direction sont comptés comme les autres.',
            'empty_message' => 'Chaque membre actif a au moins un objectif pour ce mois.',
        ];
    }

    /**
     * Rapports du jour envoyés et manquants.
     *
     * « Manquants » **exclut les absences approuvées et les jours non travaillés** (AC 29) : un
     * rapport non attendu ne peut pas être manquant. Un jour fermé rend la liste vide par
     * construction, et le message le dit — le vide par absence d'attente ne se confond jamais avec
     * le vide par absence de donnée (SOC-06).
     *
     * @return array<string, mixed>
     */
    public function dailyReports(): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $date = $today->toDateString();
        $isWorkingDay = app(CalendarService::class)->isWorkingDay($today);

        if (! $isWorkingDay) {
            return [
                'title' => 'Rapports du jour',
                'expected' => 0,
                'sent' => 0,
                'missing' => 0,
                'is_working_day' => false,
                'date' => $date,
                'url' => route('daily-reports.index', absolute: false),
                'empty_message' => "Aujourd'hui n'est pas un jour travaillé : aucun rapport n'est attendu.",
            ];
        }

        $absentUserIds = Absence::query()
            ->where('state', AbsenceState::Approuvee->value)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $expected = (int) User::permission('rapport_quotidien.creer')
            ->where('state', UserState::Actif)
            ->whereKeyNot($absentUserIds)
            ->count();

        $sent = (int) DailyReport::query()
            ->whereDate('report_date', $date)
            ->whereIn('state', [
                DailyReportState::Envoye->value,
                DailyReportState::EnRetard->value,
                DailyReportState::Valide->value,
            ])
            ->whereNotIn('author_id', $absentUserIds)
            ->distinct('author_id')
            ->count('author_id');

        return [
            'title' => 'Rapports du jour',
            'expected' => $expected,
            'sent' => $sent,
            'missing' => max(0, $expected - $sent),
            'is_working_day' => true,
            'date' => $date,
            'url' => route('daily-reports.index', absolute: false),
            'note' => 'Les absences approuvées et les jours non travaillés ne comptent pas comme manquants.',
            'empty_message' => 'Aucun rapport n’est attendu aujourd’hui.',
        ];
    }

    /**
     * Objectifs verts, orange, rouges et bloqués. Le regroupement reprend la tonalité déjà définie
     * par {@see ObjectiveState::tone()} : une seule règle de couleur dans le produit, et toujours
     * doublée d'un libellé.
     *
     * @return array<string, mixed>
     */
    public function objectivesByState(): array
    {
        $month = CarbonImmutable::now(self::TIMEZONE)->startOfMonth();
        /** @var array<string, int> $counts */
        $counts = Objective::query()
            ->whereBetween('due_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->selectRaw('state, COUNT(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();

        $groups = ['vert' => 0, 'orange' => 0, 'rouge' => 0, 'bloque' => 0];

        foreach ($counts as $state => $total) {
            $case = ObjectiveState::from((string) $state);

            if ($case === ObjectiveState::Bloque) {
                $groups['bloque'] += $total;

                continue;
            }

            if (array_key_exists($case->tone(), $groups)) {
                $groups[$case->tone()] += $total;
            }
        }

        return [
            'title' => 'Objectifs du mois',
            'total' => array_sum($groups),
            'items' => [
                ['key' => 'vert', 'label' => 'Atteints', 'count' => $groups['vert']],
                ['key' => 'orange', 'label' => 'En cours ou partiellement atteints', 'count' => $groups['orange']],
                ['key' => 'rouge', 'label' => 'Non atteints', 'count' => $groups['rouge']],
                ['key' => 'bloque', 'label' => 'Bloqués', 'count' => $groups['bloque']],
            ],
            'url' => route('objectives.summary', absolute: false),
            'empty_message' => 'Aucun objectif n’a d’échéance ce mois-ci.',
        ];
    }

    /** @return array<string, mixed> */
    public function lateProjects(): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
        $projects = Project::query()
            ->whereIn('status', [
                ProjectStatus::Prevu->value,
                ProjectStatus::Actif->value,
                ProjectStatus::Bloque->value,
                ProjectStatus::EnValidation->value,
            ])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->orderBy('end_date')
            ->get(['id', 'name', 'end_date', 'status']);

        return [
            'title' => 'Projets en retard',
            'count' => $projects->count(),
            'items' => $projects
                ->map(static fn (Project $project): array => [
                    'id' => (int) $project->getKey(),
                    'name' => (string) $project->name,
                    'end_date' => $project->end_date?->toDateString(),
                    'status_label' => $project->status->label(),
                ])
                ->all(),
            'url' => route('projects.index', absolute: false),
            'empty_message' => 'Aucun projet n’a dépassé sa date de fin.',
        ];
    }

    /**
     * Stagiaires par tuteur. **Tout tuteur ayant atteint la limite est signalé visuellement et par
     * un libellé** (AC 27, FR170) : `at_limit` porte le repère visuel, `limit_label` porte le texte,
     * et le texte suffit à comprendre sans la couleur (SOC-10).
     *
     * @return array<string, mixed>
     */
    public function internsByTutor(): array
    {
        $summary = $this->tutorCapacity->loadSummary();
        $atLimit = array_values(array_filter(
            $summary,
            static fn (array $tutor): bool => $tutor['at_limit'],
        ));

        return [
            'title' => 'Stagiaires par tuteur',
            'limit' => $this->tutorCapacity->limit(),
            'items' => $summary,
            'at_limit_count' => count($atLimit),
            'at_limit_label' => $atLimit === []
                ? 'Aucun tuteur n’a atteint la limite de stagiaires actifs.'
                : sprintf(
                    '%d tuteur%s %s atteint la limite de stagiaires actifs.',
                    count($atLimit),
                    count($atLimit) > 1 ? 's' : '',
                    count($atLimit) > 1 ? 'ont' : 'a',
                ),
            'url' => route('tutors.capacity', absolute: false),
            'empty_message' => 'Aucun tuteur n’encadre de stagiaire pour le moment.',
        ];
    }

    /**
     * Charges du mois : charges fixes actives, c'est-à-dire l'assiette de l'alerte (AC 25).
     *
     * @return array<string, mixed>
     */
    public function monthCharges(): array
    {
        $baseline = $this->alertLevelService->baseline();

        return [
            'title' => 'Charges du mois',
            'amount' => $baseline,
            'amount_label' => Money::from($baseline)->format(),
            'url' => route('fixed-charges.index', absolute: false),
            'note' => 'Somme des charges fixes actives du paramétrage.',
            'empty_message' => 'Aucune charge fixe active n’est enregistrée.',
        ];
    }

    /** @return array<string, mixed> */
    public function availableBalance(): array
    {
        $total = 0;

        foreach (Account::query()->where('state', 'active')->get(['id', 'opening_balance_amount']) as $account) {
            $total += $account->currentBalance();
        }

        return [
            'title' => 'Solde disponible',
            'amount' => $total,
            'amount_label' => Money::from(max(0, $total))->format(),
            'is_negative' => $total < 0,
            'url' => route('financial-accounts.index', absolute: false),
            'empty_message' => 'Aucun compte financier actif n’est enregistré.',
        ];
    }

    /**
     * Réserve et nombre de mois de charges qu'elle couvre (AC 25).
     *
     * @return array<string, mixed>
     */
    public function reserveBlock(): array
    {
        $amount = $this->reserve->currentAmount();
        $baseline = $this->alertLevelService->baseline();
        $coveredMonths = $baseline > 0 ? round($amount / $baseline, 1) : 0.0;

        return [
            'title' => 'Réserve',
            'amount' => $amount,
            'amount_label' => Money::from($amount)->format(),
            'covered_months' => $coveredMonths,
            'covered_months_label' => $baseline > 0
                ? sprintf('%s mois de charges couverts', str_replace('.', ',', (string) $coveredMonths))
                : 'Nombre de mois incalculable : aucune charge fixe active',
            'url' => route('reserve.index', absolute: false),
            'empty_message' => 'La réserve n’a encore reçu aucune allocation.',
        ];
    }
}
