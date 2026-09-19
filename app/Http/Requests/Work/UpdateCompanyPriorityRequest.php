<?php

namespace App\Http\Requests\Work;

use App\Models\Work\CompanyPriority;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $priority = $this->route('companyPriority');

        return $priority instanceof CompanyPriority && ($this->user()?->can('update', $priority) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...StoreCompanyPriorityRequest::fields(), 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}
