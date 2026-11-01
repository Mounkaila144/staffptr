<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Client;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+227[0-9]{8}$/', Rule::unique('clients', 'phone')],
            'contact' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Saisissez les 8 chiffres du numéro après +227, par exemple 96 11 22 33.',
            'phone.unique' => 'Un client utilise déjà ce numéro de téléphone.',
        ];
    }

    protected function prepareForValidation(): void
    {
        try {
            $this->merge(['phone' => PhoneNumber::normalize((string) $this->input('phone'))]);
        } catch (InvalidArgumentException) {
            // The validation rule returns the field error without leaking an exception.
        }
    }
}
