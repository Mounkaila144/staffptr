<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\CapitalContribution;
use Illuminate\Foundation\Http\FormRequest;

class StoreCapitalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CapitalContribution::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contribution_amount' => ['required', 'integer', 'min:1'],
            'purpose' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'ulid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contribution_amount.required' => 'Indiquez le montant versé, en francs.',
            'contribution_amount.min' => 'Le montant versé doit être supérieur à zéro.',
            'purpose.required' => 'Expliquez à quoi cet argent est destiné.',
        ];
    }
}
