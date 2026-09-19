<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\MonthlyReport;
use Illuminate\Foundation\Http\FormRequest;

class PrepareMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MonthlyReport::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['month' => ['required', 'date_format:Y-m']];
    }
}
