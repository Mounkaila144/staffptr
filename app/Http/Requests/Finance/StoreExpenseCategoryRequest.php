<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExpenseCategory::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('expense_categories', 'name')],
            'is_essential' => ['required', 'boolean'],
        ];
    }
}
