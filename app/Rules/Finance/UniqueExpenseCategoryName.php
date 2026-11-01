<?php

namespace App\Rules\Finance;

use App\Models\Finance\ExpenseCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Unicité du nom de catégorie sans tenir compte de la casse.
 *
 * `Rule::unique` comparait les noms à l'octet près : « salaires » coexistait avec « Salaires »
 * dans la liste des catégories proposées au demandeur.
 */
class UniqueExpenseCategoryName implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $exists = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('Une catégorie porte déjà ce nom.');
        }
    }
}
