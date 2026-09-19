<?php

namespace App\Services\Finance;

use App\Enums\FinancialAccountState;
use App\Enums\FinancialMovementDirection;
use App\Models\Finance\Account;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class AccountService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @return list<array{id: int, type: string, label: string, opening_balance_amount: int, opening_balance_date: string, current_balance_amount: int, current_balance: string, state: string, deactivation_reason: string|null}>
     */
    public function forManagement(User $viewer): array
    {
        $accounts = Account::query()
            ->visibleTo($viewer)
            ->withSum([
                'movements as credit_amount' => static fn ($query) => $query->where(
                    'direction',
                    FinancialMovementDirection::Credit->value,
                ),
            ], 'movement_amount')
            ->withSum([
                'movements as debit_amount' => static fn ($query) => $query->where(
                    'direction',
                    FinancialMovementDirection::Debit->value,
                ),
            ], 'movement_amount')
            ->orderBy('label')
            ->get();
        $result = [];

        foreach ($accounts as $account) {
            $balance = $account->opening_balance_amount
                + (int) ($account->getAttribute('credit_amount') ?? 0)
                - (int) ($account->getAttribute('debit_amount') ?? 0);
            $result[] = [
                'id' => (int) $account->getKey(),
                'type' => $account->type->value,
                'label' => $account->label,
                'opening_balance_amount' => $account->opening_balance_amount,
                'opening_balance_date' => $account->opening_balance_date->format('Y-m-d'),
                'current_balance_amount' => $balance,
                'current_balance' => $this->formatSignedAmount($balance),
                'state' => $account->state->value,
                'deactivation_reason' => $account->deactivation_reason,
            ];
        }

        return $result;
    }

    /** @param array{type: string, label: string, opening_balance_amount: int, opening_balance_date: string} $attributes */
    public function create(array $attributes, User $actor): Account
    {
        $account = new Account([
            ...Arr::only($attributes, ['type', 'opening_balance_amount', 'opening_balance_date']),
            'label' => trim($attributes['label']),
            'state' => FinancialAccountState::Active->value,
        ]);

        return DB::connection($account->getConnectionName())->transaction(function () use ($account, $actor): Account {
            $this->auditLogger->runExplicitly(
                auditable: $account,
                operation: fn (): bool => $account->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'financial_account_created',
                newValues: $account->getAttributes(),
                reason: "Création d'un compte financier.",
            );

            return $account;
        });
    }

    public function deactivate(Account $account, string $reason, User $actor): Account
    {
        return DB::connection($account->getConnectionName())->transaction(function () use ($account, $reason, $actor): Account {
            $locked = Account::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $oldValues = Arr::only($locked->getRawOriginal(), ['state', 'deactivation_reason', 'deactivated_at']);
            $locked->fill([
                'state' => FinancialAccountState::Inactive->value,
                'deactivation_reason' => trim($reason),
                'deactivated_at' => now('UTC'),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $locked,
                operation: fn (): bool => $locked->saveOrFail(),
                actorId: (int) $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'financial_account_deactivated',
                oldValues: $oldValues,
                newValues: Arr::only($locked->getAttributes(), ['state', 'deactivation_reason', 'deactivated_at']),
                reason: trim($reason),
            );

            return $locked;
        });
    }

    private function formatSignedAmount(int $amount): string
    {
        return ($amount < 0 ? '−' : '').Money::from(abs($amount))->format();
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
