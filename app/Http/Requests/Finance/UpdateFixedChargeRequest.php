<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\FixedCharge;
use Illuminate\Validation\Rule;

class UpdateFixedChargeRequest extends StoreFixedChargeRequest
{
    public function authorize(): bool
    {
        $charge = $this->route('fixedCharge');

        return $charge instanceof FixedCharge && ($this->user()?->can('update', $charge) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $charge = $this->route('fixedCharge');

        return [
            'label' => [
                'required',
                'string',
                'max:120',
                Rule::unique('fixed_charges', 'label')->ignore($charge instanceof FixedCharge ? $charge->getKey() : null),
            ],
            'monthly_amount' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'preview_token' => ['required', 'string', 'size:48'],
        ];
    }

    protected function previewMatches(string $action): bool
    {
        $charge = $this->route('fixedCharge');

        return $charge instanceof FixedCharge
            && parent::previewMatches("update:{$charge->getKey()}");
    }
}
