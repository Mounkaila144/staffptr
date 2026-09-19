<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\Internship;
use Illuminate\Foundation\Http\FormRequest;

class ActivateInternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('activate', Internship::class) ?? false;
    }

    /**
     * L'activation ne porte aucune donnée : les trois conditions sont lues de l'état du système,
     * jamais transmises par le formulaire (AC 16).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
