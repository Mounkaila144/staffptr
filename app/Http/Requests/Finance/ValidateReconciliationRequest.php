<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Reconciliation;
use Illuminate\Foundation\Http\FormRequest;

class ValidateReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('reconciliation');

        return $item instanceof Reconciliation && ($this->user()?->can('validate', $item) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
