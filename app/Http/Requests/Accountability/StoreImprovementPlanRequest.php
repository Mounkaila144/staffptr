<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\ImprovementPlan;
use App\Models\Accountability\WeeklyReview;
use Illuminate\Foundation\Http\FormRequest;

class StoreImprovementPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('weeklyReview');

        return $review instanceof WeeklyReview && ($this->user()?->can('createFrom', [ImprovementPlan::class, $review]) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'support_provided' => ['required', 'string', 'max:5000'],
            'actions' => ['required', 'array', 'min:1', 'max:20'],
            'actions.*.description' => ['required', 'string', 'max:2000'],
            'actions.*.due_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'start_date.required' => "Indiquez le début de l'accompagnement.",
            'end_date.required' => "Indiquez la fin de l'accompagnement.",
            'end_date.after_or_equal' => 'La date de fin vient après la date de début.',
            'support_provided.required' => "Décrivez l'aide fournie à la personne accompagnée.",
            'actions.required' => 'Décrivez au moins une action convenue.',
            'actions.min' => 'Décrivez au moins une action convenue.',
            'actions.*.description.required' => "Décrivez l'action convenue.",
            'actions.*.due_date.required' => 'Indiquez la date prévue pour cette action.',
        ];
    }

    /**
     * Les bornes de durée restent vérifiées côté serveur par le service, qui fait foi
     * indépendamment de ce formulaire (AC 9).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'start_date' => 'date de début',
            'end_date' => 'date de fin',
            'support_provided' => 'aide fournie',
        ];
    }
}
