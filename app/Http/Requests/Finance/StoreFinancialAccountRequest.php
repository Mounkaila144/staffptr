<?php

namespace App\Http\Requests\Finance;

use App\Enums\FinancialAccountType;
use App\Models\Finance\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Account::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(FinancialAccountType::class)],
            'label' => ['required', 'string', 'max:120', Rule::unique('accounts', 'label')],
            'opening_balance_amount' => ['required', 'integer', 'min:0'],
            'opening_balance_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
