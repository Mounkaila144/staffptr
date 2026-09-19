<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentMode;
use App\Models\Finance\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('is_active', true)],
            'contract_id' => ['nullable', 'integer', 'required_without:project_id', Rule::exists('contracts', 'id')],
            'project_id' => ['nullable', 'integer', 'required_without:contract_id', Rule::exists('projects', 'id')],
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'received_amount' => ['required', 'integer', 'min:1'],
            'received_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'payment_mode' => ['required', Rule::enum(PaymentMode::class)],
            'reference' => ['nullable', 'string', 'max:160'],
            'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')],
            'idempotency_key' => ['required', 'ulid'],
        ];
    }
}
