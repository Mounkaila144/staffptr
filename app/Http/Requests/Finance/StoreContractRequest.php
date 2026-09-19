<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contract::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('is_active', true)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'reference' => ['required', 'string', 'max:80', Rule::unique('contracts', 'reference')],
            'title' => ['required', 'string', 'max:200'],
            'expected_total_amount' => ['required', 'integer', 'min:0'],
            'forecast_profit_amount' => ['required', 'integer', 'min:0', 'lte:expected_total_amount'],
            'contributor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'has_execution' => ['required', 'boolean'],
            'executor_ids' => ['array'],
            'executor_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }
}
