<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Contract;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends StoreContractRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract && ($this->user()?->can('update', $contract) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $contract = $this->route('contract');
        $rules['reference'] = ['required', 'string', 'max:80', Rule::unique('contracts', 'reference')->ignore($contract instanceof Contract ? $contract->getKey() : null)];

        return $rules;
    }
}
