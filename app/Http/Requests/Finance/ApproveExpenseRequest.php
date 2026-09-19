<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Expense;
use App\Services\Finance\ExpenseApprovalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('depense.approuver') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['return_to' => ['nullable', 'string', 'in:index,decision']];
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
}
