<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ExpenseCategory;
use App\Rules\Finance\UniqueExpenseCategoryName;
use Illuminate\Foundation\Http\FormRequest;

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
                new UniqueExpenseCategoryName($category instanceof ExpenseCategory ? (int) $category->getKey() : null),
            ],
            'is_essential' => ['required', 'boolean'],
        ];
    }
}
