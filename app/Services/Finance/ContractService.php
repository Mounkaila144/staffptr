<?php

namespace App\Services\Finance;

use App\Enums\ContractState;
use App\Enums\ExpenseState;
use App\Enums\PaymentState;
use App\Enums\RelationType;
use App\Enums\UserState;
use App\Models\Finance\Contract;
use App\Models\Finance\ContractExecutor;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use App\Support\ShareCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ContractService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ShareCalculator $shareCalculator,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forManagement(): array
    {
        $contracts = Contract::query()
            ->with(['client', 'project', 'contributor.person', 'executors.user.person'])
            ->orderByDesc('created_at')
            ->get();

        return $contracts->map(fn (Contract $contract): array => $this->summary($contract))->all();
    }

    /**
     * @param  array{client_id: int, project_id?: int|null, reference: string, title: string, expected_total_amount: int, forecast_profit_amount: int, contributor_id?: int|null, has_execution: bool, executor_ids?: list<int>, starts_on?: string|null, ends_on?: string|null}  $data
     */
    public function create(array $data, User $actor): Contract
    {
        $executorIds = $this->validateParticipants($data);
        $contract = new Contract($this->contractAttributes($data));

        return DB::connection($contract->getConnectionName())->transaction(function () use ($contract, $executorIds, $actor): Contract {
            $this->auditLogger->runExplicitly(
                auditable: $contract,
                operation: function () use ($contract, $executorIds): bool {
                    $saved = $contract->saveOrFail();
                    $this->replaceExecutors($contract, $executorIds);

                    return $saved;
                },
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'contract_created',
                newValues: [...$contract->getAttributes(), 'executor_ids' => $executorIds],
                reason: 'Création du contrat et de sa répartition prévisionnelle.',
            );

            return $contract->load(['client', 'project', 'contributor.person', 'executors.user.person']);
        });
    }

    /**
     * @param  array{client_id: int, project_id?: int|null, reference: string, title: string, expected_total_amount: int, forecast_profit_amount: int, contributor_id?: int|null, has_execution: bool, executor_ids?: list<int>, starts_on?: string|null, ends_on?: string|null}  $data
     */
    public function update(Contract $contract, array $data, User $actor): Contract
    {
        $executorIds = $this->validateParticipants($data);

        return DB::connection($contract->getConnectionName())->transaction(function () use ($contract, $data, $executorIds, $actor): Contract {
            $locked = Contract::query()->whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== ContractState::Active) {
                throw ValidationException::withMessages(['contract' => 'Un contrat clôturé ou annulé est immuable.']);
            }

            $oldValues = Arr::only($locked->getRawOriginal(), array_keys($this->contractAttributes($data)));
            $oldValues['executor_ids'] = $locked->executors()->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
            $locked->fill($this->contractAttributes($data));

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: function () use ($locked, $executorIds): bool {
                    $saved = $locked->saveOrFail();
                    $this->replaceExecutors($locked, $executorIds);

                    return $saved;
                },
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'contract_updated',
                oldValues: $oldValues,
                newValues: [...Arr::only($locked->getAttributes(), array_keys($this->contractAttributes($data))), 'executor_ids' => $executorIds],
                reason: 'Modification du contrat et de sa répartition prévisionnelle.',
            );

            return $locked->load(['client', 'project', 'contributor.person', 'executors.user.person']);
        });
    }

    public function close(Contract $contract, string $reason, User $actor): Contract
    {
        return DB::connection($contract->getConnectionName())->transaction(function () use ($contract, $reason, $actor): Contract {
            $locked = Contract::query()->whereKey($contract->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->state !== ContractState::Active) {
                throw ValidationException::withMessages(['contract' => 'Seul un contrat actif peut être clôturé.']);
            }
            $oldValues = Arr::only($locked->getRawOriginal(), ['state', 'closed_at', 'closure_reason']);
            $locked->forceFill([
                'state' => ContractState::Closed->value,
                'closed_at' => CarbonImmutable::now('UTC'),
                'closure_reason' => trim($reason),
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'contract_closed',
                oldValues: $oldValues,
                newValues: Arr::only($locked->getAttributes(), ['state', 'closed_at', 'closure_reason']),
                reason: trim($reason),
            );

            return $locked;
        });
    }

    /** @return array<string, mixed> */
    public function summary(Contract $contract): array
    {
        $executorIds = $contract->executors->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $forecast = $this->shareCalculator->calculate(
            $contract->forecast_profit_amount,
            $contract->contributor_id === null ? null : (int) $contract->contributor_id,
            $contract->has_execution,
            $executorIds,
        );
        $receipts = (int) $contract->payments()
            ->where('state', PaymentState::Validated->value)
            ->whereNull('reversal_of_id')
            ->sum('received_amount');
        $directCosts = (int) $contract->expenses()
            ->where('state', ExpenseState::Payee->value)
            ->whereNull('counter_entry_of_id')
            ->sum('requested_amount');
        $directCosts -= (int) $contract->expenses()
            ->whereNotNull('counter_entry_of_id')
            ->sum('requested_amount');
        $directCosts = max(0, $directCosts);
        $actualProfit = $receipts - $directCosts;
        $distributableActualProfit = max(0, $actualProfit);
        $actual = $this->shareCalculator->calculate(
            $distributableActualProfit,
            $contract->contributor_id === null ? null : (int) $contract->contributor_id,
            $contract->has_execution,
            $executorIds,
        );
        $actualByKey = [];
        foreach ($actual['shares'] as $share) {
            $actualByKey[$share['beneficiary_key'].'|'.$share['share_type']] = $share['share_amount'];
        }

        $names = $this->participantNames($contract);
        $entitlements = $contract->shareEntitlements()->whereNull('reversed_at')->get()->groupBy(
            fn ($entitlement): string => $entitlement->beneficiary_key.'|'.$entitlement->share_type->value,
        );
        $shares = [];
        foreach ($forecast['shares'] as $share) {
            $key = $share['beneficiary_key'].'|'.$share['share_type'];
            $actualAmount = $actualByKey[$key] ?? 0;
            $beneficiaryEntitlements = $entitlements->get($key, collect());
            $dueAmount = (int) $beneficiaryEntitlements->sum('share_amount');
            $paidAmount = (int) $beneficiaryEntitlements->sum('paid_amount');
            $shares[] = [
                ...$share,
                'beneficiary_name' => $names[$share['beneficiary_key']] ?? $share['beneficiary_key'],
                'forecast_amount_label' => Money::from($share['share_amount'])->format(),
                'actual_amount' => $actualAmount,
                'actual_amount_label' => Money::from($actualAmount)->format(),
                'variance_amount' => $actualAmount - $share['share_amount'],
                'variance_amount_label' => $this->signedAmount($actualAmount - $share['share_amount']),
                'due_amount' => $dueAmount,
                'due_amount_label' => Money::from($dueAmount)->format(),
                'paid_amount' => $paidAmount,
                'paid_amount_label' => Money::from($paidAmount)->format(),
                'remaining_amount' => max(0, $dueAmount - $paidAmount),
                'remaining_amount_label' => Money::from(max(0, $dueAmount - $paidAmount))->format(),
            ];
        }

        $variance = $actualProfit - $contract->forecast_profit_amount;
        $variancePercentage = $contract->forecast_profit_amount > 0
            ? (int) round(($variance * 100) / $contract->forecast_profit_amount)
            : null;

        return [
            'id' => (int) $contract->getKey(),
            'client_id' => (int) $contract->client_id,
            'client_name' => $contract->client->name,
            'project_id' => $contract->project_id === null ? null : (int) $contract->project_id,
            'project_name' => $contract->project?->name,
            'reference' => $contract->reference,
            'title' => $contract->title,
            'expected_total_amount' => $contract->expected_total_amount,
            'expected_total_amount_label' => Money::from($contract->expected_total_amount)->format(),
            'forecast_profit_amount' => $contract->forecast_profit_amount,
            'forecast_profit_amount_label' => Money::from($contract->forecast_profit_amount)->format(),
            'contributor_id' => $contract->contributor_id === null ? null : (int) $contract->contributor_id,
            'has_execution' => $contract->has_execution,
            'executor_ids' => $executorIds,
            'state' => $contract->state->value,
            'starts_on' => $contract->starts_on?->toDateString(),
            'ends_on' => $contract->ends_on?->toDateString(),
            'received_amount' => $receipts,
            'received_amount_label' => Money::from($receipts)->format(),
            'direct_cost_amount' => $directCosts,
            'direct_cost_amount_label' => Money::from($directCosts)->format(),
            'actual_profit_amount' => $actualProfit,
            'actual_profit_amount_label' => $this->signedAmount($actualProfit),
            'variance_amount' => $variance,
            'variance_amount_label' => $this->signedAmount($variance),
            'variance_percentage' => $variancePercentage,
            'variance_percentage_label' => $variancePercentage === null ? 'Non calculable' : ($variancePercentage > 0 ? '+' : '').$variancePercentage.' %',
            'calculation_method' => $forecast['method'],
            'shares' => $shares,
            'regularization_proposal' => $contract->state === ContractState::Closed ? $this->regularizationProposal($variance) : null,
        ];
    }

    /** @param array<string, mixed> $data
     * @return list<int>
     */
    private function validateParticipants(array $data): array
    {
        $contributorId = isset($data['contributor_id'])
            ? (int) $data['contributor_id']
            : null;
        $executorIds = array_values(array_map('intval', $data['executor_ids'] ?? []));
        $hasExecution = (bool) $data['has_execution'];

        if ($contributorId !== null) {
            $validContributor = User::query()
                ->whereKey($contributorId)
                ->where('state', UserState::Actif->value)
                ->whereIn('relation_type', [RelationType::Dirigeant->value, RelationType::Employe->value])
                ->exists();
            if (! $validContributor) {
                throw ValidationException::withMessages(['contributor_id' => 'L’apporteur doit être un dirigeant ou un employé actif.']);
            }
        }

        if (! $hasExecution && $executorIds !== []) {
            throw ValidationException::withMessages(['executor_ids' => 'Un contrat sans exécution ne peut pas avoir d’exécutant.']);
        }
        if ($hasExecution && $contributorId === null) {
            $executorIds = [];
        }
        if ($hasExecution && $contributorId !== null && $executorIds === []) {
            throw ValidationException::withMessages(['executor_ids' => 'Sélectionnez au moins un associé exécutant.']);
        }
        if (count($executorIds) !== count(array_unique($executorIds))) {
            throw ValidationException::withMessages(['executor_ids' => 'Chaque associé exécutant ne peut apparaître qu’une fois.']);
        }

        if ($executorIds !== []) {
            $validIds = User::query()
                ->whereKey($executorIds)
                ->where('state', UserState::Actif->value)
                ->whereHas('roles', fn ($query) => $query->where('name', 'direction'))
                ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            if (count(array_intersect($executorIds, $validIds)) !== count($executorIds)) {
                throw ValidationException::withMessages(['executor_ids' => 'Les exécutants doivent tous être des associés actifs.']);
            }
        }

        return $executorIds;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function contractAttributes(array $data): array
    {
        return [
            'client_id' => (int) $data['client_id'],
            'project_id' => isset($data['project_id']) ? (int) $data['project_id'] : null,
            'reference' => trim((string) $data['reference']),
            'title' => trim((string) $data['title']),
            'expected_total_amount' => (int) $data['expected_total_amount'],
            'forecast_profit_amount' => (int) $data['forecast_profit_amount'],
            'contributor_id' => isset($data['contributor_id']) ? (int) $data['contributor_id'] : null,
            'has_execution' => (bool) $data['has_execution'],
            'state' => ContractState::Active->value,
            'starts_on' => $this->dateOrNull($data['starts_on'] ?? null),
            'ends_on' => $this->dateOrNull($data['ends_on'] ?? null),
        ];
    }

    /** @param list<int> $executorIds */
    private function replaceExecutors(Contract $contract, array $executorIds): void
    {
        foreach ($contract->executors()->get() as $executor) {
            $executor->forceFill(['is_active' => false, 'deactivated_at' => CarbonImmutable::now('UTC')])->saveOrFail();
        }
        foreach ($executorIds as $index => $userId) {
            ContractExecutor::query()->create([
                'contract_id' => $contract->getKey(),
                'user_id' => $userId,
                'position' => $index + 1,
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string, string> */
    private function participantNames(Contract $contract): array
    {
        $names = ['company:ptr-niger' => 'PTR Niger'];
        if ($contract->contributor !== null) {
            $names['user:'.$contract->contributor->getKey()] = (string) $contract->contributor->person->getAttribute('full_name');
        }
        foreach ($contract->executors as $executor) {
            $names['user:'.$executor->user_id] = (string) $executor->user->person->getAttribute('full_name');
        }

        return $names;
    }

    private function dateOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return array{type: string, amount: int, amount_label: string, message: string}|null */
    private function regularizationProposal(int $variance): ?array
    {
        if ($variance === 0) {
            return null;
        }

        $amount = abs($variance);
        $type = $variance > 0 ? 'complement' : 'reprise';

        return [
            'type' => $type,
            'amount' => $amount,
            'amount_label' => Money::from($amount)->format(),
            'message' => $variance > 0
                ? 'Complément de parts à proposer à la clôture via le circuit ordinaire de dépense.'
                : 'Reprise de parts à proposer à la clôture ; aucune écriture n’est appliquée automatiquement.',
        ];
    }

    private function signedAmount(int $amount): string
    {
        return ($amount < 0 ? '−' : '').Money::from(abs($amount))->format();
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
