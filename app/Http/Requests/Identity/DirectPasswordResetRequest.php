<?php

namespace App\Http\Requests\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Réinitialisation sans code WhatsApp : réservée à la direction, jamais sur son propre compte
 * (on change son propre mot de passe depuis son profil), et toujours motivée.
 */
class DirectPasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        return $actor instanceof User
            && $target instanceof User
            && $target->state !== UserState::Archive
            && ! $actor->is($target)
            && $actor->hasRole('direction')
            && $actor->can('update', $target);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'verification' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'verification.required' => 'Indiquez comment vous avez vérifié l’identité de la personne (en personne, par appel…).',
            'verification.min' => 'Le motif doit contenir au moins 10 caractères.',
            'verification.max' => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
