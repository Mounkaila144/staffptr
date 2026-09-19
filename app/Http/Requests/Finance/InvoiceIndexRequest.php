<?php

namespace App\Http\Requests\Finance;

use App\Enums\InvoiceState;
use App\Models\Finance\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Invoice::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'state' => ['nullable', Rule::enum(InvoiceState::class)],
            'sort' => ['nullable', Rule::in(['age_desc', 'outstanding_desc'])],
        ];
    }
}
