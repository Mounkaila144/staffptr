<?php

namespace App\Http\Requests\Accountability;

use App\Enums\InternshipEvaluationType;
use App\Models\Accountability\Internship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordInternshipEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $internship = $this->route('internship');

        return $internship instanceof Internship && ($this->user()?->can('manage', $internship) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(InternshipEvaluationType::class)],
            'week_start_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,'.InternshipEvaluationType::Hebdomadaire->value],
            'observed_progress' => ['required', 'string', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:5000'],
            'next_steps' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required' => 'Indiquez le type d’évaluation.',
            'week_start_date.required_if' => 'Indiquez la semaine évaluée.',
            'observed_progress.required' => 'Décrivez les progrès constatés.',
        ];
    }
}
