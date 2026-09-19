<?php

namespace App\Http\Requests\Accountability;

use App\Enums\ReviewObjectiveStatus;
use App\Models\Accountability\WeeklyReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordWeeklyReviewObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('weeklyReview');

        return $review instanceof WeeklyReview && ($this->user()?->can('update', $review) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'objective_id' => ['required', 'integer', 'exists:objectives,id'],
            'result' => ['required', 'string', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(ReviewObjectiveStatus::class)],
            'gap_cause' => ['nullable', 'string', 'max:5000'],
            'next_action' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'objective_id.required' => "Choisissez l'objectif concerné.",
            'result.required' => 'Décrivez le résultat constaté.',
            'status.required' => 'Indiquez où en est cet objectif.',
            'next_action.required' => 'Indiquez la prochaine action convenue.',
        ];
    }
}
