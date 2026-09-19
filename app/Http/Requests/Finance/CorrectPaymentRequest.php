<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Payment;

class CorrectPaymentRequest extends StorePaymentRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment instanceof Payment && ($this->user()?->can('correct', $payment) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'correction_reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}
