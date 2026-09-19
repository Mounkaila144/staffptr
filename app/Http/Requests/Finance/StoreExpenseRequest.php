<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // AC 3: creation is open to all active authenticated users
        return (int) $this->user()?->getKey() > 0;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:expense_categories,id'],
            'reason' => ['required', 'string', 'max:255'],
            'requested_amount' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'beneficiary' => ['required', 'string', 'max:255'],
            'expected_result' => ['required', 'string'],
            'project_or_contract_note' => ['nullable', 'string', 'max:255'],
            'attachment_ulid' => ['nullable', 'exists:attachments,ulid'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'La catégorie est obligatoire.',
            'category_id.exists' => 'Cette catégorie n\'existe pas.',
            'reason.required' => 'Le motif est obligatoire.',
            'requested_amount.required' => 'Le montant est obligatoire.',
            'requested_amount.integer' => 'Le montant doit être un entier.',
            'requested_amount.min' => 'Le montant doit être positif.',
            'beneficiary.required' => 'Le bénéficiaire est obligatoire.',
            'expected_result.required' => 'Le résultat attendu est obligatoire.',
        ];
    }
}
