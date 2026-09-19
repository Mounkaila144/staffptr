<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Contract;
use Illuminate\Foundation\Http\FormRequest;

class CloseContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract && ($this->user()?->can('close', $contract) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['closure_reason' => ['required', 'string', 'max:1000']];
    }
}
