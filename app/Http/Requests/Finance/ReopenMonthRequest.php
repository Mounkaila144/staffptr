<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\MonthlyReport;
use Illuminate\Foundation\Http\FormRequest;

class ReopenMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('monthlyReport');

        return $report instanceof MonthlyReport && ($this->user()?->can('reopen', $report) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
