<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\DailyReport;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('dailyReport');
        $user = $this->user();

        return $report instanceof DailyReport
            && $report->author_id === $user->getKey()
            && $user->can('rapport_quotidien.creer');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['description' => ['required', 'string', 'max:2000'], 'is_urgent' => ['required', 'boolean']];
    }
}
