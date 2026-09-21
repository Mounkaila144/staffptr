<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\CapitalContribution;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCapitalContributionRequest extends FormRequest
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
        return [];
    }
}
