<?php

namespace App\Services\Finance;

use App\Enums\ExpenseState;
use App\Enums\InvoiceState;
use App\Enums\MonthlyReportState;
use App\Enums\PaymentState;
use App\Enums\ReserveMovementType;
use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Finance\FixedCharge;
use App\Models\Finance\Invoice;
use App\Models\Finance\MonthClosure;
use App\Models\Finance\MonthlyReport;
use App\Models\Finance\Payment;
use App\Models\Finance\ReserveMovement;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class MonthlyReportService
{
    public function __construct(private AuditLogger $auditLogger, private AlertLevelService $alerts) {}

    /** @return list<array<string, mixed>> */
    public function listing(User $viewer): array
    {
        return MonthlyReport::query()->visibleTo($viewer)->with(['preparer.person', 'controller.person', 'validator.person', 'closure'])
            ->orderByDesc('month')->orderByDesc('version')->get()->map(fn (MonthlyReport $report): array => [
                'id' => (int) $report->getKey(), 'month' => $report->month->format('Y-m'),
                'month_label' => $report->month->locale('fr')->translatedFormat('F Y'), 'version' => $report->version,
                'state' => $report->state->value, 'lines' => $report->lines,
                'prepared_by' => $report->prepared_by, 'controlled_by' => $report->controlled_by,
                'validated_by' => $report->validated_by, 'alert_level' => $report->alert_level?->value,
                'is_closed' => $report->closure !== null && $report->closure->reopened_at === null,
                'is_reopened' => $report->closure !== null && $report->closure->reopened_at !== null,
            ])->all();
    }

    public function prepare(string $month, User $actor): MonthlyReport
    {
        $start = CarbonImmutable::parse($month, 'Africa/Niamey')->startOfMonth();

        return DB::transaction(function () use ($start, $actor): MonthlyReport {
            $latest = MonthlyReport::query()->whereDate('month', $start->toDateString())->orderByDesc('version')->lockForUpdate()->first();
            if ($latest !== null && $latest->state !== MonthlyReportState::Validated) {
                throw ValidationException::withMessages(['month' => 'Un rapport de ce mois attend déjà son contrôle ou sa validation.']);
            }
            if (MonthClosure::query()->currentlyClosed()->whereDate('month', $start->toDateString())->exists()) {
                throw ValidationException::withMessages(['month' => 'Ce mois est clôturé et doit être rouvert avant une nouvelle version.']);
            }
            $nextVersion = $latest === null ? 1 : $latest->version + 1;
            $report = new MonthlyReport([
                'month' => $start->toDateString(), 'version' => $nextVersion,
                'previous_id' => $latest?->getKey(), 'state' => MonthlyReportState::Draft->value,
                'lines' => $this->lines($start), 'prepared_by' => $actor->getKey(),
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $report, operation: fn (): bool => $report->saveOrFail(), actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor), action: 'monthly_report_prepared', newValues: $report->getAttributes(),
                reason: 'Préparation du rapport financier mensuel.',
            );

            return $report;
        });
    }

    public function control(MonthlyReport $report, User $actor): MonthlyReport
    {
        return DB::transaction(function () use ($report, $actor): MonthlyReport {
            $locked = MonthlyReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== MonthlyReportState::Draft) {
                throw ValidationException::withMessages(['report' => 'Seul un rapport brouillon peut être contrôlé.']);
            }
            if ((int) $locked->prepared_by === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['controlled_by' => 'Le préparateur et le contrôleur doivent être deux comptes distincts.']);
            }

            return $this->transition($locked, $actor, [
                'state' => MonthlyReportState::Controlled->value, 'controlled_by' => $actor->getKey(), 'controlled_at' => CarbonImmutable::now('UTC'),
            ], 'monthly_report_controlled', 'Contrôle par un compte distinct du préparateur.');
        });
    }

    public function validate(MonthlyReport $report, User $actor): MonthlyReport
    {
        return DB::transaction(function () use ($report, $actor): MonthlyReport {
            $locked = MonthlyReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== MonthlyReportState::Controlled) {
                throw ValidationException::withMessages(['report' => 'Le rapport doit être contrôlé avant la validation finale.']);
            }
            if (! $actor->hasRole('direction')) {
                throw ValidationException::withMessages(['validated_by' => 'La validation finale appartient à la direction.']);
            }
            $assessment = $this->alerts->assess($locked->month);
            $locked = $this->transition($locked, $actor, [
                'state' => MonthlyReportState::Validated->value, 'validated_by' => $actor->getKey(),
                'validated_at' => CarbonImmutable::now('UTC'), 'alert_level' => $assessment->level->value,
                'alert_source_date' => $assessment->sourceDate->toDateString(),
            ], 'monthly_report_validated', 'Validation finale et clôture mensuelle par la direction.');
            $version = ((int) MonthClosure::query()->whereDate('month', $locked->month->toDateString())->max('version')) + 1;
            $closure = new MonthClosure([
                'month' => $locked->month->toDateString(), 'version' => $version, 'monthly_report_id' => $locked->getKey(),
                'closed_by' => $actor->getKey(), 'closed_at' => CarbonImmutable::now('UTC'), 'frozen_alert_level' => $assessment->level->value,
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $closure, operation: fn (): bool => $closure->saveOrFail(), actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor), action: 'month_closed', newValues: $closure->getAttributes(),
                reason: 'Clôture après validation du rapport financier.',
            );

            return $locked;
        });
    }

    public function reopen(MonthlyReport $report, string $reason, User $actor): MonthClosure
    {
        if (! $actor->hasRole('direction')) {
            throw ValidationException::withMessages(['reopened_by' => 'Seule la direction peut rouvrir un mois.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Le motif de réouverture est obligatoire.']);
        }

        return DB::transaction(function () use ($report, $reason, $actor): MonthClosure {
            $closure = MonthClosure::query()->currentlyClosed()->where('monthly_report_id', $report->getKey())->lockForUpdate()->firstOrFail();
            $oldValues = $closure->getRawOriginal();
            $closure->forceFill(['reopened_by' => $actor->getKey(), 'reopened_at' => CarbonImmutable::now('UTC'), 'reopen_reason' => trim($reason)]);
            $this->auditLogger->runExplicitly(
                auditable: $closure, operation: fn (): bool => $closure->saveOrFail(), actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor), action: 'month_reopened', oldValues: $oldValues,
                newValues: $closure->getAttributes(), reason: trim($reason),
            );

            return $closure;
        });
    }

    /** @return list<array{key: string, label: string, value: int|float, value_label: string, period: string, method: string, not_applicable: bool}> */
    public function lines(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $start->endOfMonth();
        $invoiced = (int) Invoice::query()->where('state', '!=', InvoiceState::Annulee->value)->whereBetween('issued_on', [$start->toDateString(), $end->toDateString()])->sum('total_amount');
        $received = (int) Payment::query()->where('state', PaymentState::Validated->value)->whereNull('reversal_of_id')->whereBetween('received_on', [$start->toDateString(), $end->toDateString()])->sum('received_amount');
        $receivables = $this->receivablesAt($end);
        $direct = $this->expenseNet($start, $end, true);
        $salaries = (int) FixedCharge::query()->active()->whereRaw('LOWER(label) LIKE ?', ['%salaire%'])->sum('monthly_amount');
        $fixed = (int) FixedCharge::query()->active()->sum('monthly_amount');
        $allExpenses = $this->expenseNet($start, $end, false);
        $cash = (int) Account::query()->get()->sum(fn (Account $account): int => max(0, $account->balanceAt($end->toDateString())));
        $reserve = $this->reserveAt($end);
        $covered = $fixed > 0 ? round($reserve / $fixed, 2) : 0.0;
        $period = $start->toDateString().' au '.$end->toDateString();

        return [
            $this->line('invoiced', 'CA facturé', $invoiced, $period, 'Somme des factures non annulées émises pendant le mois.'),
            $this->line('received', 'Encaissements reçus', $received, $period, 'Somme des encaissements validés, hors contre-écritures.'),
            $this->line('receivables', 'Créances clients', $receivables, $period, 'Factures émises à la fin du mois moins encaissements imputés à cette date.'),
            $this->line('direct_costs', 'Coûts directs des projets', $direct, $period, 'Dépenses payées imputées à un projet ou contrat, nettes des contre-écritures.'),
            $this->line('salaries', 'Salaires et rémunérations', $salaries, $period, 'Charge fixe active dont le libellé correspond aux salaires.'),
            $this->line('fixed_charges', 'Charges fixes', $fixed, $period, 'Somme des charges fixes actives du paramétrage.'),
            $this->line('taxes', 'Taxes et charges sociales', 0, $period, 'Poste non implémenté dans le MVP.', true),
            $this->line('debts', 'Dettes', 0, $period, 'Poste non implémenté dans le MVP.', true),
            $this->line('cash', 'Trésorerie totale', $cash, $period, 'Somme des soldes calculés des comptes à la fin du mois.'),
            $this->line('result', 'Résultat estimé', $received - $allExpenses, $period, 'Encaissements reçus moins toutes les dépenses payées nettes.'),
            $this->line('reserve', 'Réserve disponible', $reserve, $period, 'Allocations et reconstitutions moins usages et renversements approuvés.'),
            ['key' => 'covered_months', 'label' => 'Mois de charges couverts', 'value' => $covered, 'value_label' => $covered.' mois', 'period' => $period, 'method' => 'Réserve disponible divisée par les charges fixes actives.', 'not_applicable' => false],
        ];
    }

    private function expenseNet(CarbonImmutable $start, CarbonImmutable $end, bool $directOnly): int
    {
        $query = Expense::query()->where('state', ExpenseState::Payee->value)->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()]);
        if ($directOnly) {
            $query->where(fn ($q) => $q->whereNotNull('project_id')->orWhereNotNull('contract_id'));
        }

        return max(0, (int) $query->selectRaw('SUM(CASE WHEN counter_entry_of_id IS NULL THEN requested_amount ELSE -requested_amount END) AS net')->value('net'));
    }

    private function receivablesAt(CarbonImmutable $end): int
    {
        $total = 0;
        foreach (Invoice::query()->where('state', '!=', InvoiceState::Annulee->value)->whereDate('issued_on', '<=', $end->toDateString())->get() as $invoice) {
            $paid = (int) $invoice->payments()->where('state', PaymentState::Validated->value)->whereNull('reversal_of_id')->whereDate('received_on', '<=', $end->toDateString())->sum('received_amount');
            $total += max(0, $invoice->total_amount - $paid);
        }

        return $total;
    }

    private function reserveAt(CarbonImmutable $end): int
    {
        $credits = (int) ReserveMovement::query()->where('approval_state', 'approved')->whereDate('occurred_on', '<=', $end->toDateString())->whereIn('type', [ReserveMovementType::Allocation->value, ReserveMovementType::Reconstitution->value])->sum('movement_amount');
        $debits = (int) ReserveMovement::query()->where('approval_state', 'approved')->whereDate('occurred_on', '<=', $end->toDateString())->whereIn('type', [ReserveMovementType::Usage->value, ReserveMovementType::Reversal->value])->sum('movement_amount');

        return max(0, $credits - $debits);
    }

    /** @return array{key: string, label: string, value: int, value_label: string, period: string, method: string, not_applicable: bool} */
    private function line(string $key, string $label, int $value, string $period, string $method, bool $notApplicable = false): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value, 'value_label' => ($value < 0 ? '−' : '').Money::from(abs($value))->format(), 'period' => $period, 'method' => $method, 'not_applicable' => $notApplicable];
    }

    /** @param array<string, mixed> $attributes */
    private function transition(MonthlyReport $report, User $actor, array $attributes, string $action, string $reason): MonthlyReport
    {
        $oldValues = $report->getRawOriginal();
        $report->forceFill($attributes);
        $this->auditLogger->runExplicitly(auditable: $report, operation: fn (): bool => $report->saveOrFail(), actorId: (int) $actor->getKey(), actorLabel: $this->actorLabel($actor), action: $action, oldValues: $oldValues, newValues: $report->getAttributes(), reason: $reason);

        return $report;
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
