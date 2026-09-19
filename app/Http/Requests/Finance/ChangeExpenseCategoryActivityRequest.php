<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;

class ChangeExpenseCategoryActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('expenseCategory');

        return $category instanceof ExpenseCategory && ($this->user()?->can('update', $category) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
