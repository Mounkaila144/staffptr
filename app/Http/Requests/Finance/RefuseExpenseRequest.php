<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Expense;
use App\Services\Finance\ExpenseApprovalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RefuseExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('depense.approuver') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'return_to' => ['nullable', 'string', 'in:index,decision'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $expense = $this->route('expense');
            $user = $this->user();

            if ($expense instanceof Expense
                && $user !== null
                && (int) $expense->requester_id === (int) $user->getKey()) {
                $validator->errors()->add('approval', ExpenseApprovalService::REQUESTER_MESSAGE);
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Le motif du refus est obligatoire.',
            'reason.min' => 'Le motif du refus doit contenir au moins 3 caractères.',
            'reason.max' => 'Le motif du refus ne peut pas dépasser 255 caractères.',
        ];
    }
}
