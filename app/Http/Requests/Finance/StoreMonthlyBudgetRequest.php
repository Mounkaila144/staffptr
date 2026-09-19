<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\MonthlyBudget;
use Illuminate\Foundation\Http\FormRequest;

class StoreMonthlyBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MonthlyBudget::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'month' => ['required', 'date_format:Y-m'],
            'budget_amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
