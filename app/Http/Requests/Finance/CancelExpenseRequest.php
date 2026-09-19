<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CancelExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by ExpensePolicy::cancel()
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'min:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'Le motif d\'annulation est obligatoire.',
            'cancel_reason.min' => 'Le motif doit contenir au moins 3 caractères.',
        ];
    }
}
