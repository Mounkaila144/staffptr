<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\CapitalContribution;
use Illuminate\Foundation\Http\FormRequest;

class RefuseCapitalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contribution = $this->route('capitalContribution');

        return $contribution instanceof CapitalContribution
            && ($this->user()?->can('decide', $contribution) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'refusal_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'refusal_reason.required' => 'Expliquez pourquoi cet apport est refusé.',
        ];
    }
}
