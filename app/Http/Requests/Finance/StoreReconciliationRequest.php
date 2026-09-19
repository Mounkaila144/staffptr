<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Reconciliation;
use Illuminate\Foundation\Http\FormRequest;

class StoreReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reconciliation::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'physical_balance_amount' => ['required', 'integer', 'min:0'],
            'difference_explanation' => ['nullable', 'string', 'max:2000'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'corrective_action' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
