<?php

namespace App\Services\Finance;

use App\Enums\PaymentState;
use App\Models\Finance\Contract;
use App\Models\Finance\Expense;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\Payment;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use App\Support\ShareCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class ShareEntitlementService
{
    public function __construct(
        private ShareCalculator $calculator,
        private AuditLogger $auditLogger,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forViewer(User $viewer): array
    {
        return ShareEntitlement::query()->visibleTo($viewer)
            ->whereNull('reversed_at')
            ->with(['contract.client', 'payment', 'beneficiary.person'])
            ->orderByDesc('source_received_on')->orderBy('id')->get()
            ->map(fn (ShareEntitlement $entitlement): array => [
                'id' => (int) $entitlement->getKey(),
                'contract_id' => (int) $entitlement->contract_id,
                'contract_reference' => $entitlement->contract->reference,
                'client_name' => $entitlement->contract->client->name,
                'receipt_number' => $entitlement->payment->receipt_number,
                'beneficiary_id' => $entitlement->beneficiary_id,
                'beneficiary_name' => $entitlement->beneficiary?->person->full_name ?? 'PTR Niger',
                'share_type' => $entitlement->share_type->value,
                'base_amount' => $entitlement->base_amount,
                'base_amount_label' => Money::from($entitlement->base_amount)->format(),
                'rate_basis_points' => $entitlement->rate_basis_points,
                'rate_divisor' => $entitlement->rate_divisor,
                'rate_label' => $entitlement->rate_divisor > 1
                    ? sprintf('%g %% ÷ %d', $entitlement->rate_basis_points / 100, $entitlement->rate_divisor)
                    : sprintf('%g %%', $entitlement->rate_basis_points / 100),
                'share_amount' => $entitlement->share_amount,
                'share_amount_label' => Money::from($entitlement->share_amount)->format(),
                'paid_amount' => $entitlement->paid_amount,
                'paid_amount_label' => Money::from($entitlement->paid_amount)->format(),
                'remaining_amount' => $entitlement->remainingAmount(),
                'remaining_amount_label' => Money::from($entitlement->remainingAmount())->format(),
                'calculation_method' => $entitlement->calculation_method,
                'period_start' => $entitlement->period_start->toDateString(),
                'period_end' => $entitlement->period_end->toDateString(),
                'source_received_on' => $entitlement->source_received_on->toDateString(),
                'can_request_payment' => (int) $entitlement->beneficiary_id === (int) $viewer->getKey() && $entitlement->remainingAmount() > 0,
            ])->all();
    }

    /** @return list<ShareEntitlement> */
    public function createForPayment(Payment $payment, Contract $contract): array
    {
        if ($payment->getConnection()->transactionLevel() < 1) {
            throw new RuntimeException('Le calcul des parts doit appartenir à la transaction d’encaissement.');
        }
        if ($contract->expected_total_amount < 1) {
            throw new RuntimeException('Le montant total attendu du contrat doit être positif.');
        }

        $previousReceipts = (int) $contract->payments()
            ->whereKeyNot($payment->getKey())
            ->where('state', PaymentState::Validated->value)
            ->whereNull('reversal_of_id')
            ->sum('received_amount');
        $before = min($contract->expected_total_amount, $previousReceipts);
        $after = min($contract->expected_total_amount, $previousReceipts + $payment->received_amount);
        $beforeBase = intdiv($contract->forecast_profit_amount * $before, $contract->expected_total_amount);
        $afterBase = intdiv($contract->forecast_profit_amount * $after, $contract->expected_total_amount);
        $base = $afterBase - $beforeBase;
        $executorIds = $contract->executors()->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $calculation = $this->calculator->calculate(
            $base,
            $contract->contributor_id === null ? null : (int) $contract->contributor_id,
            $contract->has_execution,
            $executorIds,
        );
        $periodStart = $contract->starts_on?->toDateString() ?? $payment->received_on->toDateString();
        $entitlements = [];

        foreach ($calculation['shares'] as $share) {
            $entitlements[] = ShareEntitlement::query()->create([
                'contract_id' => $contract->getKey(),
                'payment_id' => $payment->getKey(),
                'beneficiary_id' => $share['beneficiary_id'],
                'beneficiary_key' => $share['beneficiary_key'],
                'share_type' => $share['share_type'],
                'base_amount' => $base,
                'rate_basis_points' => $share['rate_basis_points'],
                'rate_divisor' => $share['rate_divisor'],
                'share_amount' => $share['share_amount'],
                'paid_amount' => 0,
                'calculation_method' => $calculation['method'],
                'period_start' => $periodStart,
                'period_end' => $payment->received_on->toDateString(),
                'source_received_on' => $payment->received_on->toDateString(),
            ]);
        }

        return $entitlements;
    }

    public function reverseForPayment(Payment $original, Payment $reversal): void
    {
        foreach ($original->shareEntitlements()->whereNull('reversed_at')->lockForUpdate()->get() as $entitlement) {
            $entitlement->forceFill([
                'reversed_at' => CarbonImmutable::now('UTC'),
                'reversal_payment_id' => $reversal->getKey(),
            ])->saveOrFail();
        }
    }

    /**
     * Aucune garde de niveau d'alerte ici, et c'est délibéré : **le calcul et le versement des
     * parts de 10 % et 30 % restent possibles en alerte rouge** (story 9.1 AC 10, RM-14, FR165,
     * CONTRA-07). Une part est une créance acquise sur un encaissement déjà réalisé ; la retenir
     * reviendrait à faire porter la tension de trésorerie de l'entreprise à une personne, ce que
     * le produit s'interdit (AC 12, RM-18).
     *
     * Le test bloquant « parts 10 %/30 % payables en rouge » verrouille cette absence de garde.
     * Ajouter une condition d'alerte dans cette méthode ou dans {@see createForPayment} fait
     * échouer la porte qualité.
     */
    public function requestPayment(ShareEntitlement $entitlement, User $requester): Expense
    {
        return DB::transaction(function () use ($entitlement, $requester): Expense {
            $locked = ShareEntitlement::query()->with(['beneficiary.person', 'contract'])->whereKey($entitlement->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->reversed_at !== null || $locked->remainingAmount() === 0) {
                throw ValidationException::withMessages(['share' => 'Cette part n’a plus de montant payable.']);
            }
            if ((int) $locked->beneficiary_id !== (int) $requester->getKey()) {
                throw ValidationException::withMessages(['share' => 'Vous ne pouvez demander le versement que de votre propre part.']);
            }
            if (Expense::query()->where('share_entitlement_id', $locked->getKey())->whereNotIn('state', ['refusee', 'annulee'])->exists()) {
                throw ValidationException::withMessages(['share' => 'Une demande de versement est déjà en cours pour cette part.']);
            }
            $category = ExpenseCategory::query()->where('name', 'Fonctionnement courant')->where('is_active', true)->firstOrFail();
            $expense = new Expense([
                'requester_id' => $requester->getKey(),
                'category_id' => $category->getKey(),
                'reason' => 'Versement de part — contrat '.$locked->contract->reference,
                'requested_amount' => $locked->remainingAmount(),
                'beneficiary' => $locked->beneficiary?->person->full_name ?? "Compte #{$requester->getKey()}",
                'expected_result' => sprintf(
                    'Verser la part calculée sur une base de %s au taux de %g %% ÷ %d.',
                    Money::from($locked->base_amount)->format(),
                    $locked->rate_basis_points / 100,
                    $locked->rate_divisor,
                ),
                'project_or_contract_note' => $locked->contract->reference,
                'contract_id' => $locked->contract_id,
                'beneficiary_user_id' => $locked->beneficiary_id,
                'share_entitlement_id' => $locked->getKey(),
            ]);
            $expense->forceFill(['state' => 'demandee']);
            $this->auditLogger->runExplicitly(
                auditable: $expense,
                operation: fn (): bool => $expense->saveOrFail(),
                actorId: (int) $requester->getKey(),
                actorLabel: $requester->person()->value('full_name') ?? "Compte #{$requester->getKey()}",
                action: 'share_payment_requested',
                newValues: [...$expense->getAttributes(), 'base_amount' => $locked->base_amount, 'rate_basis_points' => $locked->rate_basis_points, 'rate_divisor' => $locked->rate_divisor],
                reason: 'Versement demandé par le bénéficiaire via le circuit ordinaire de dépense.',
            );

            return $expense;
        });
    }
}
