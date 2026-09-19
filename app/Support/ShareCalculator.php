<?php

namespace App\Support;

use App\Enums\ShareType;
use InvalidArgumentException;

final readonly class ShareCalculator
{
    /**
     * @param  list<int>  $executorIds  Ordre stable affiché ; le premier reçoit tout reliquat.
     * @return array{base_amount: int, method: string, shares: list<array{rank: int, share_type: string, beneficiary_key: string, beneficiary_id: int|null, rate_basis_points: int, rate_divisor: int, rate_label: string, share_amount: int}>}
     */
    public function calculate(
        int $baseAmount,
        ?int $contributorId,
        bool $hasExecution,
        array $executorIds = [],
    ): array {
        Money::from($baseAmount);
        $this->validateExecutors($executorIds);

        if ($contributorId === null) {
            return $this->withoutContributor($baseAmount);
        }

        if (! $hasExecution) {
            return $this->withoutExecution($baseAmount, $contributorId);
        }

        if ($executorIds === []) {
            throw new InvalidArgumentException('Un contrat avec exécution exige au moins un exécutant.');
        }

        return $this->withExecution($baseAmount, $contributorId, $executorIds);
    }

    /** @return array{base_amount: int, method: string, shares: list<array{rank: int, share_type: string, beneficiary_key: string, beneficiary_id: int|null, rate_basis_points: int, rate_divisor: int, rate_label: string, share_amount: int}>} */
    private function withoutContributor(int $baseAmount): array
    {
        return [
            'base_amount' => $baseAmount,
            'method' => 'Sans apporteur : 100 % PTR Niger. Division entière ; tout reliquat revient au bénéficiaire de rang 1.',
            'shares' => [[
                'rank' => 1,
                'share_type' => ShareType::PtrNiger->value,
                'beneficiary_key' => 'company:ptr-niger',
                'beneficiary_id' => null,
                'rate_basis_points' => 10_000,
                'rate_divisor' => 1,
                'rate_label' => '100 %',
                'share_amount' => $baseAmount,
            ]],
        ];
    }

    /** @return array{base_amount: int, method: string, shares: list<array{rank: int, share_type: string, beneficiary_key: string, beneficiary_id: int|null, rate_basis_points: int, rate_divisor: int, rate_label: string, share_amount: int}>} */
    private function withoutExecution(int $baseAmount, int $contributorId): array
    {
        $amounts = Money::from($baseAmount)->allocateByBasisPoints([
            'contributor' => 1_000,
            'ptr' => 9_000,
        ]);

        return [
            'base_amount' => $baseAmount,
            'method' => 'Apporteur sans exécution : 10 % apporteur / 90 % PTR Niger. Division entière ; tout reliquat revient à l’apporteur de rang 1.',
            'shares' => [
                $this->userShare(1, ShareType::Contributor, $contributorId, 1_000, 1, '10 %', $amounts['contributor']),
                $this->ptrShare(2, 9_000, $amounts['ptr'], '90 %'),
            ],
        ];
    }

    /**
     * @param  list<int>  $executorIds
     * @return array{base_amount: int, method: string, shares: list<array{rank: int, share_type: string, beneficiary_key: string, beneficiary_id: int|null, rate_basis_points: int, rate_divisor: int, rate_label: string, share_amount: int}>}
     */
    private function withExecution(int $baseAmount, int $contributorId, array $executorIds): array
    {
        $buckets = Money::from($baseAmount)->allocateByBasisPoints([
            'contributor' => 1_000,
            'ptr' => 6_000,
            'executors' => 3_000,
        ]);
        $executorAmounts = Money::from($buckets['executors'])->splitEqually(count($executorIds));
        $shares = [
            $this->userShare(1, ShareType::Contributor, $contributorId, 1_000, 1, '10 %', $buckets['contributor']),
            $this->ptrShare(2, 6_000, $buckets['ptr'], '60 %'),
        ];

        foreach ($executorIds as $index => $executorId) {
            $shares[] = $this->userShare(
                rank: $index + 3,
                type: ShareType::Executor,
                userId: $executorId,
                rateBasisPoints: 3_000,
                rateDivisor: count($executorIds),
                rateLabel: sprintf('30 %% ÷ %d', count($executorIds)),
                amount: $executorAmounts[$index],
            );
        }

        return [
            'base_amount' => $baseAmount,
            'method' => 'Apporteur avec exécution : 10 % apporteur / 60 % PTR Niger / 30 % exécutants à parts égales. Division entière ; le reliquat revient au bénéficiaire de rang 1 de chaque répartition.',
            'shares' => $shares,
        ];
    }

    /** @return array{rank: int, share_type: string, beneficiary_key: string, beneficiary_id: int, rate_basis_points: int, rate_divisor: int, rate_label: string, share_amount: int} */
    private function userShare(
        int $rank,
        ShareType $type,
        int $userId,
        int $rateBasisPoints,
        int $rateDivisor,
        string $rateLabel,
        int $amount,
    ): array {
        return [
            'rank' => $rank,
            'share_type' => $type->value,
            'beneficiary_key' => "user:{$userId}",
            'beneficiary_id' => $userId,
            'rate_basis_points' => $rateBasisPoints,
            'rate_divisor' => $rateDivisor,
            'rate_label' => $rateLabel,
            'share_amount' => $amount,
        ];
    }

    /** @return array{rank: int, share_type: string, beneficiary_key: string, beneficiary_id: null, rate_basis_points: int, rate_divisor: int, rate_label: string, share_amount: int} */
    private function ptrShare(int $rank, int $rateBasisPoints, int $amount, string $rateLabel): array
    {
        return [
            'rank' => $rank,
            'share_type' => ShareType::PtrNiger->value,
            'beneficiary_key' => 'company:ptr-niger',
            'beneficiary_id' => null,
            'rate_basis_points' => $rateBasisPoints,
            'rate_divisor' => 1,
            'rate_label' => $rateLabel,
            'share_amount' => $amount,
        ];
    }

    /** @param list<int> $executorIds */
    private function validateExecutors(array $executorIds): void
    {
        if (count($executorIds) !== count(array_unique($executorIds))) {
            throw new InvalidArgumentException('Un exécutant ne peut apparaître qu’une fois.');
        }

        foreach ($executorIds as $executorId) {
            if ($executorId < 1) {
                throw new InvalidArgumentException('Chaque exécutant doit être identifié.');
            }
        }
    }
}
