<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('expenseCategory');

        return $category instanceof ExpenseCategory && ($this->user()?->can('update', $category) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('expenseCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('expense_categories', 'name')->ignore($category instanceof ExpenseCategory ? $category->getKey() : null),
            ],
            'is_essential' => ['required', 'boolean'],
        ];
    }
}
