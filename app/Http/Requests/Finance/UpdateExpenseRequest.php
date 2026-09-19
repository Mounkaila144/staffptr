<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by ExpensePolicy::update()
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:expense_categories,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'requested_amount' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'expected_result' => ['nullable', 'string'],
            'project_or_contract_note' => ['nullable', 'string', 'max:255'],
            'attachment_ulid' => ['nullable', 'exists:attachments,ulid'],
        ];
    }
}
