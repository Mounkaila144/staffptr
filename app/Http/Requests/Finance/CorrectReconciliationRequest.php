<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Reconciliation;

class CorrectReconciliationRequest extends StoreReconciliationRequest
{
    public function authorize(): bool
    {
        $item = $this->route('reconciliation');

        return $item instanceof Reconciliation && ($this->user()?->can('correct', $item) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'correction_reason' => ['required', 'string', 'max:2000']];
    }
}
