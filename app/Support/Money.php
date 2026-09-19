<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class Money
{
    private int $amount;

    public function __construct(mixed $amount)
    {
        if (! is_int($amount) || $amount < 0) {
            throw new InvalidArgumentException('Le montant doit être un entier XOF positif ou nul.');
        }

        $this->amount = $amount;
    }

    public static function from(mixed $amount): self
    {
        return new self($amount);
    }

    public function value(): int
    {
        return $this->amount;
    }

    public function format(): string
    {
        return number_format($this->amount, 0, '', "\u{202F}").' FCFA';
    }

    /**
     * Répartit la valeur selon des points de base totalisant 10 000. Le reliquat entier revient à
     * la première clé, qui représente le bénéficiaire de rang 1.
     *
     * @param  array<string, int>  $basisPoints
     * @return array<string, int>
     */
    public function allocateByBasisPoints(array $basisPoints): array
    {
        if ($basisPoints === [] || array_sum($basisPoints) !== 10_000) {
            throw new InvalidArgumentException('Les taux doivent totaliser exactement 10 000 points de base.');
        }

        $allocation = [];
        $allocated = 0;

        foreach ($basisPoints as $beneficiary => $rate) {
            if ($rate < 0 || $rate > 10_000) {
                throw new InvalidArgumentException('Chaque taux doit être compris entre 0 et 10 000 points de base.');
            }

            $allocation[$beneficiary] = intdiv($this->amount * $rate, 10_000);
            $allocated += $allocation[$beneficiary];
        }

        $firstBeneficiary = array_key_first($basisPoints);
        $allocation[$firstBeneficiary] += $this->amount - $allocated;

        return $allocation;
    }

    /**
     * @return list<int>
     */
    public function splitEqually(int $beneficiaryCount): array
    {
        if ($beneficiaryCount < 1) {
            throw new InvalidArgumentException('Le nombre de bénéficiaires doit être positif.');
        }

        $share = intdiv($this->amount, $beneficiaryCount);
        $remainder = $this->amount % $beneficiaryCount;
        $shares = array_fill(0, $beneficiaryCount, $share);
        $shares[0] += $remainder;

        return $shares;
    }
}
