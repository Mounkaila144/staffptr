<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMode;
use App\Models\Finance\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense && ($this->user()?->can('pay', $expense) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'paid_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'payment_mode' => ['required', Rule::enum(PaymentMode::class)],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')],
            'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')],
            'is_advance_reimbursement' => ['required', 'boolean'],
            'payment_idempotency_key' => ['required', 'ulid'],
        ];
    }
}
