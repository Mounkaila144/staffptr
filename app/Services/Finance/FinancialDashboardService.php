<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Enums\InvoiceState;
use App\Enums\PaymentState;
use App\Enums\ReconciliationState;
use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\MonthlyBudget;
use App\Models\Finance\Payment;
use App\Models\Finance\Reconciliation;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Lecture agrégée du tableau de bord financier (AC 19 à 24, AC 43, FR171, FR172).
 *
 * **Cloisonnement.** La règle FR172 est appliquée par omission et non par masquage : un bloc dont
 * la permission manque n'est pas rendu vide, il est absent de la charge utile. Le client ne reçoit
 * donc jamais la forme d'une donnée qu'il n'a pas le droit de voir (AC 23, AC 43). C'est la raison
 * pour laquelle chaque bloc est construit derrière son propre `can()` plutôt que filtré après coup.
 *
 * **Coût.** Chaque bloc est une agrégation SQL, jamais une collection chargée puis réduite en PHP,
 * et le résultat est mis en cache par utilisateur et par bloc avec un plafond de cinq minutes
 * (architecture § 19.1). L'invalidation à l'écriture reste portée par les services propriétaires ;
 * le TTL n'est qu'un filet.
 */
final readonly class FinancialDashboardService
{
    private const TIMEZONE = 'Africa/Niamey';

    private const TTL_SECONDS = 300;

    public function __construct(
        private ReserveService $reserve,
        private AlertLevelService $alertLevelService,
    ) {}

    /**
     * Blocs autorisés pour ce compte. Les clés absentes sont exactement les blocs interdits.
     *
     * @return array<string, array<string, mixed>>
     */
    public function blocks(User $viewer): array
    {
        $blocks = [];

        foreach ($this->definitions() as $key => $definition) {
            if (! $viewer->can($definition['permission'])) {
                continue;
            }

            $blocks[$key] = $this->cached($viewer, $key, $definition['build']);
        }

        return $blocks;
    }

    /**
     * Le niveau d'alerte accompagne toujours le tableau de bord financier (AC 4, AC 6).
     *
     * @return array{level: string, level_label: string, month: string, month_label: string, baseline: int, collections: int, previous_collections: int, method: string, source_date: string, frozen: bool}
     */
    public function alert(): array
    {
        return $this->alertLevelService->assess()->toArray();
    }

    /**
     * Table des blocs : permission exigée et constructeur. Une seule source pour l'écran, les
     * tests d'autorisation et la documentation.
     *
     * @return array<string, array{permission: string, build: Closure(): array<string, mixed>}>
     */
    public function definitions(): array
    {
        return [
            'account_balances' => [
                'permission' => 'compte_financier.consulter',
                'build' => fn (): array => $this->accountBalances(),
            ],
            'pending_expenses' => [
                'permission' => 'depense.consulter',
                'build' => fn (): array => $this->pendingExpenses(),
            ],
            'month_collections' => [
                'permission' => 'encaissement.consulter',
                'build' => fn (): array => $this->monthCollections(),
            ],
            'overdue_receivables' => [
                'permission' => 'facture.consulter',
                'build' => fn (): array => $this->overdueReceivables(),
            ],
            'reconciliation_gaps' => [
                'permission' => 'rapprochement.consulter',
                'build' => fn (): array => $this->reconciliationGaps(),
            ],
            'budget_versus_actual' => [
                'permission' => 'budget_financier.consulter',
                'build' => fn (): array => $this->budgetVersusActual(),
            ],
            'reserve' => [
                'permission' => 'reserve.consulter',
                'build' => fn (): array => $this->reserveBlock(),
            ],
            'share_commitments' => [
                'permission' => 'part.consulter',
                'build' => fn (): array => $this->shareCommitments(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function accountBalances(): array
    {
        $accounts = Account::query()
            ->select(['id', 'label', 'type', 'state', 'opening_balance_amount'])
            ->orderBy('label')
            ->get();
        $items = [];
        $total = 0;

        foreach ($accounts as $account) {
            $balance = $account->currentBalance();
            $total += $balance;
            $items[] = [
                'id' => (int) $account->getKey(),
                'label' => (string) $account->label,
                'amount' => $balance,
                'amount_label' => Money::from(max(0, $balance))->format(),
                'is_negative' => $balance < 0,
                'state_label' => $account->state->label(),
            ];
        }

        return [
            'title' => 'Soldes par compte',
            'total' => $total,
            'total_label' => Money::from(max(0, $total))->format(),
            'items' => $items,
            'url' => route('financial-accounts.index', absolute: false),
            'empty_message' => "Aucun compte financier n'est enregistré. Créez-en un pour suivre les soldes.",
        ];
    }

    /** @return array<string, mixed> */
    public function pendingExpenses(): array
    {
        $pending = Expense::query()->where('state', ExpenseState::Demandee->value);

        return [
            'title' => 'Dépenses en attente',
            'count' => (int) $pending->clone()->count(),
            'amount' => $amount = (int) $pending->clone()->sum('requested_amount'),
            'amount_label' => Money::from($amount)->format(),
            'url' => route('expenses.approvals.index', absolute: false),
            'empty_message' => "Aucune dépense n'attend de décision.",
        ];
    }

    /** @return array<string, mixed> */
    public function monthCollections(): array
    {
        $month = CarbonImmutable::now(self::TIMEZONE)->startOfMonth();
        $query = Payment::query()
            ->where('state', PaymentState::Validated->value)
            ->whereBetween('received_on', [$month->toDateString(), $month->endOfMonth()->toDateString()]);

        return [
            'title' => 'Encaissements du mois',
            'count' => (int) $query->clone()->count(),
            'amount' => $amount = (int) $query->clone()->sum('received_amount'),
            'amount_label' => Money::from($amount)->format(),
            'month_label' => $month->format('m/Y'),
            'url' => route('payments.index', absolute: false),
            'empty_message' => "Aucun encaissement n'a encore été enregistré ce mois-ci.",
        ];
    }

    /** @return array<string, mixed> */
    public function overdueReceivables(): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
        $invoices = Invoice::query()
            ->whereIn('state', [InvoiceState::Impayee->value, InvoiceState::PartiellementPayee->value])
            ->whereDate('due_on', '<', $today)
            ->with('client:id,name')
            ->orderBy('due_on')
            ->get();
        $amount = 0;
        $items = [];

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->outstandingAmount();
            $amount += $outstanding;
            $items[] = [
                'id' => (int) $invoice->getKey(),
                'number' => (string) $invoice->number,
                'client' => (string) $invoice->client->name,
                'due_on' => $invoice->due_on->toDateString(),
                'amount_label' => Money::from($outstanding)->format(),
            ];
        }

        return [
            'title' => 'Créances échues',
            'count' => $invoices->count(),
            'amount' => $amount,
            'amount_label' => Money::from($amount)->format(),
            'items' => array_slice($items, 0, 5),
            'url' => route('invoices.index', absolute: false),
            'empty_message' => 'Aucune facture échue reste impayée.',
        ];
    }

    /** @return array<string, mixed> */
    public function reconciliationGaps(): array
    {
        $query = Reconciliation::query()
            ->where('state', ReconciliationState::Validated->value)
            ->where('difference_amount', '>', 0);

        return [
            'title' => 'Écarts de rapprochement',
            'count' => (int) $query->clone()->count(),
            'amount' => $amount = (int) $query->clone()->sum('difference_amount'),
            'amount_label' => Money::from($amount)->format(),
            'url' => route('reconciliations.index', absolute: false),
            'empty_message' => 'Aucun écart de rapprochement ne reste à expliquer.',
        ];
    }

    /** @return array<string, mixed> */
    public function budgetVersusActual(): array
    {
        $month = CarbonImmutable::now(self::TIMEZONE)->startOfMonth();
        $budget = (int) MonthlyBudget::query()
            ->whereDate('month', $month->toDateString())
            ->sum('budget_amount');
        $actual = (int) Expense::query()
            ->whereIn('state', [ExpenseState::Approuvee->value, ExpenseState::Payee->value])
            ->whereBetween('created_at', [
                $month->setTimezone('UTC'),
                $month->endOfMonth()->setTimezone('UTC'),
            ])
            ->sum('requested_amount');

        return [
            'title' => 'Budget contre réalisé',
            'budget_amount' => $budget,
            'budget_amount_label' => Money::from($budget)->format(),
            'actual_amount' => $actual,
            'actual_amount_label' => Money::from($actual)->format(),
            'is_over_budget' => $budget > 0 && $actual > $budget,
            // Le dépassement est dit en toutes lettres : jamais porté par la couleur seule (SOC-10).
            'status_label' => match (true) {
                $budget === 0 => "Aucun budget n'est défini pour ce mois",
                $actual > $budget => 'Budget dépassé',
                default => 'Dans le budget',
            },
            'month_label' => $month->format('m/Y'),
            'url' => route('monthly-budgets.index', absolute: false),
            'empty_message' => "Aucun budget mensuel n'est défini pour ce mois.",
        ];
    }

    /** @return array<string, mixed> */
    public function reserveBlock(): array
    {
        $amount = $this->reserve->currentAmount();

        return [
            'title' => 'Réserve disponible',
            'amount' => $amount,
            'amount_label' => Money::from($amount)->format(),
            'url' => route('reserve.index', absolute: false),
            'empty_message' => "La réserve n'a encore reçu aucune allocation.",
        ];
    }

    /**
     * Total des engagements de parts restant à verser sur les contrats en cours (AC 20, FR171).
     *
     * Une part contre-passée ne figure plus dans l'engagement : elle a été annulée avec son
     * encaissement.
     *
     * @return array<string, mixed>
     */
    public function shareCommitments(): array
    {
        $query = ShareEntitlement::query()
            ->whereNull('reversed_at')
            ->whereColumn('paid_amount', '<', 'share_amount')
            ->whereHas('contract', function ($contract): void {
                $contract->where('state', 'active');
            });

        $remaining = (int) $query->clone()->sum('share_amount') - (int) $query->clone()->sum('paid_amount');

        return [
            'title' => 'Engagements de parts restant à verser',
            'count' => (int) $query->clone()->count(),
            'amount' => $remaining,
            'amount_label' => Money::from(max(0, $remaining))->format(),
            'url' => route('shares.index', absolute: false),
            'empty_message' => 'Aucune part ne reste à verser sur les contrats en cours.',
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    private function cached(User $viewer, string $block, callable $build): array
    {
        /** @var array<string, mixed> $payload */
        $payload = Cache::remember(
            "dash:{$viewer->getKey()}:finance:{$block}",
            self::TTL_SECONDS,
            static fn (): array => $build(),
        );

        return $payload;
    }
}
