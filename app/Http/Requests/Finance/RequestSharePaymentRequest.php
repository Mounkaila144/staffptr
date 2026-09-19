<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ShareEntitlement;
use Illuminate\Foundation\Http\FormRequest;

class RequestSharePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $share = $this->route('shareEntitlement');

        return $share instanceof ShareEntitlement && ($this->user()?->can('requestPayment', $share) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
