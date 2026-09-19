<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Expense;
use Illuminate\Foundation\Http\FormRequest;

class CancelPaidExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense && ($this->user()?->can('cancelPayment', $expense) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'payment_idempotency_key' => ['required', 'ulid'],
        ];
    }
}
