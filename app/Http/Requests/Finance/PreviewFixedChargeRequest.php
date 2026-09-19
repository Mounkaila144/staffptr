<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\FixedCharge;
use Illuminate\Foundation\Http\FormRequest;

class PreviewFixedChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $identifier = $this->input('fixed_charge_id');

        if ($identifier === null || $identifier === '') {
            return $this->user()?->can('create', FixedCharge::class) ?? false;
        }

        $charge = FixedCharge::query()->find($identifier);

        return $charge instanceof FixedCharge && ($this->user()?->can('update', $charge) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fixed_charge_id' => ['nullable', 'integer', 'exists:fixed_charges,id'],
            'label' => ['required', 'string', 'max:120'],
            'monthly_amount' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
