<?php

namespace App\Http\Requests\Work;

use App\Models\Work\CompanyPriority;
use Illuminate\Foundation\Http\FormRequest;

class CompanyPriorityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CompanyPriority::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['month' => ['nullable', 'date_format:Y-m']];
    }
}
