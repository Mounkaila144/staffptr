<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachExpensePaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense && ($this->user()?->can('attachPaymentProof', $expense) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attachment_ulid' => ['required', 'string', 'size:26', Rule::exists('attachments', 'ulid')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attachment_ulid.required' => 'Ajoutez le justificatif de paiement avant de l’enregistrer.',
        ];
    }
}
