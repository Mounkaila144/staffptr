<?php

namespace App\Http\Requests\Accountability;

use App\Http\Requests\Work\StoreObjectiveRequest;
use App\Models\Accountability\InternshipIntakeForm;
use App\Models\Identity\User;
use App\Models\Work\Objective;

/**
 * Objectif ajouté depuis la fiche d'entrée : le responsable est toujours le stagiaire de la fiche.
 *
 * Le compte n'est pas envoyé par l'écran mais lu sur la fiche, puis soumis à la même règle de
 * périmètre que l'écran des objectifs.
 */
class StoreIntakeObjectiveRequest extends StoreObjectiveRequest
{
    public function authorize(): bool
    {
        $form = $this->route('internshipIntakeForm');
        $user = $this->user();

        return $form instanceof InternshipIntakeForm
            && $user instanceof User
            && $user->can('view', $form)
            && $user->can('create', Objective::class);
    }

    /**
     * Un objectif de départ ne peut pas être déjà échu : l'écran acceptait une échéance passée,
     * et le stagiaire commençait son stage avec un objectif en retard.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['due_date'] = [...$rules['due_date'], 'after_or_equal:today'];

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'due_date.after_or_equal' => 'L’échéance ne peut pas être dans le passé.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $form = $this->route('internshipIntakeForm');

        if ($form instanceof InternshipIntakeForm) {
            $this->merge(['user_id' => $form->candidate_user_id]);
        }
    }
}
