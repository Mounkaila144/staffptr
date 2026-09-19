<?php

namespace App\Http\Requests\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmPasswordResetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && $target->state !== UserState::Archive
            && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation_code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation_code.required' => 'Saisissez le code reçu sur le WhatsApp du compte cible.',
            'confirmation_code.regex' => 'Le code de confirmation doit contenir exactement 6 chiffres.',
        ];
    }
}
