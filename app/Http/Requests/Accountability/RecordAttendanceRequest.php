<?php

namespace App\Http\Requests\Accountability;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Arrivée ou départ d'un stagiaire. Aucune heure n'est reçue : elle vient de l'horloge du
 * serveur, pour qu'aucun enregistrement ne puisse être antidaté.
 */
class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('arrivee_depart.enregistrer') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
