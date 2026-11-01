<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ExpenseCategory;
use App\Rules\Finance\UniqueExpenseCategoryName;
use Illuminate\Foundation\Http\FormRequest;

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
            'name' => ['required', 'string', 'max:160', new UniqueExpenseCategoryName],
            'is_essential' => ['required', 'boolean'],
        ];
    }
}
