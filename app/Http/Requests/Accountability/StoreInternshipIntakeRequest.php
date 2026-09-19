<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\InternshipIntakeForm;
use Illuminate\Foundation\Http\FormRequest;

class StoreInternshipIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InternshipIntakeForm::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'candidate_user_id' => ['required', 'integer', 'exists:users,id'],
            'manager_id' => ['required', 'integer', 'exists:users,id'],
            'tutor_id' => ['required', 'integer', 'exists:users,id'],
            'real_need' => ['required', 'string', 'max:5000'],
            'mission' => ['required', 'string', 'max:5000'],
            'duration_weeks' => ['required', 'integer', 'min:1', 'max:104'],
            'tools' => ['required', 'string', 'max:5000'],
            // Trois résultats attendus au minimum (AC 14), redit par le service côté serveur.
            'outcomes' => ['required', 'array', 'min:3', 'max:10'],
            'outcomes.*' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'candidate_user_id.required' => 'Choisissez le compte du stagiaire.',
            'manager_id.required' => 'Choisissez le responsable du stage.',
            'tutor_id.required' => 'Désignez le tuteur du stagiaire.',
            'real_need.required' => 'Décrivez le besoin réel auquel ce stage répond.',
            'mission.required' => 'Décrivez la mission confiée.',
            'duration_weeks.required' => 'Indiquez la durée du stage en semaines.',
            'tools.required' => 'Indiquez les outils mis à disposition.',
            'outcomes.required' => 'Indiquez les trois résultats attendus.',
            'outcomes.min' => 'Une fiche d’entrée porte au moins trois résultats attendus.',
            'outcomes.*.required' => 'Décrivez ce résultat attendu.',
        ];
    }
}
