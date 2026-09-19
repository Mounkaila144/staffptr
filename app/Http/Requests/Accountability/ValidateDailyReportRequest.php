<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\DailyReport;
use Illuminate\Foundation\Http\FormRequest;

class ValidateDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('dailyReport');

        return $report instanceof DailyReport && ($this->user()?->can('review', $report) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
