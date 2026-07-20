<?php

namespace App\Http\Requests\Identity;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'max:20',
                static function (string $_attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        $fail(PhoneNumber::INVALID_MESSAGE);

                        return;
                    }

                    try {
                        PhoneNumber::normalize($value);
                    } catch (InvalidArgumentException) {
                        $fail(PhoneNumber::INVALID_MESSAGE);
                    }
                },
            ],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.required' => 'Indiquez votre numéro de téléphone.',
            'password.required' => 'Indiquez votre mot de passe.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (! is_string($phone)) {
            return;
        }

        try {
            $this->merge(['phone' => PhoneNumber::normalize($phone)]);
        } catch (InvalidArgumentException) {
            // La validation produit le message utilisateur sans transformer l'erreur en 500.
        }
    }
}
