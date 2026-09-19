<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\Internship;
use Illuminate\Foundation\Http\FormRequest;

class SaveInternshipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $internship = $this->route('internship');

        return $internship instanceof Internship && ($this->user()?->can('manage', $internship) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'skills_to_learn' => ['required', 'string', 'max:5000'],
            'objectives' => ['required', 'string', 'max:5000'],
            'weekly_tasks' => ['required', 'string', 'max:5000'],
            'expected_evidence' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'skills_to_learn.required' => 'Indiquez les compétences à apprendre.',
            'objectives.required' => 'Indiquez les objectifs du stage.',
            'weekly_tasks.required' => 'Indiquez les tâches hebdomadaires.',
            'expected_evidence.required' => 'Indiquez les preuves attendues.',
        ];
    }
}
