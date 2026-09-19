<?php

namespace App\Http\Requests\Work;

use App\Models\Work\CompanyPriority;
use Illuminate\Foundation\Http\FormRequest;

class CancelCompanyPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $priority = $this->route('companyPriority');

        return $priority instanceof CompanyPriority && ($this->user()?->can('cancel', $priority) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
