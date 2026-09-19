<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\FixedCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFixedChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FixedCharge::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120', Rule::unique('fixed_charges', 'label')],
            'monthly_amount' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'preview_token' => ['required', 'string', 'size:48'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->previewMatches('create')) {
                $validator->errors()->add('preview_token', "Calculez l'impact sur la réserve avant de confirmer.");
            }
        });
    }

    protected function previewMatches(string $action): bool
    {
        $preview = $this->session()->get('fixed_charge_preview');

        return is_array($preview)
            && ($preview['action'] ?? null) === $action
            && ($preview['label'] ?? null) === trim((string) $this->input('label'))
            && ($preview['monthly_amount'] ?? null) === (int) $this->input('monthly_amount')
            && ($preview['is_active'] ?? null) === $this->boolean('is_active')
            && ($preview['token'] ?? null) === $this->input('preview_token');
    }
}
