<?php

namespace App\Http\Requests\Accountability;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rapport_quotidien.consulter') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'vue' => ['nullable', Rule::in(['quotidienne', 'hebdomadaire', 'mensuelle'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
