<?php

namespace App\Http\Requests\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StoreAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_mode' => ['required', Rule::in(['new', 'existing'])],
            'person_id' => ['nullable', 'required_if:person_mode,existing', 'integer', 'exists:people,id'],
            'full_name' => ['nullable', 'required_if:person_mode,new', 'string', 'max:255'],
            'first_seen_at' => ['nullable', 'required_if:person_mode,new', 'date_format:Y-m-d', 'before_or_equal:today'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+227[0-9]{8}$/',
                Rule::unique('users', 'phone')->where(
                    static fn (Builder $query): Builder => $query->where('state', '!=', UserState::Archive->value),
                ),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
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
            // La règle de format produit ensuite l'erreur sans transformer l'exception en erreur 500.
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => PhoneNumber::INVALID_MESSAGE,
            'phone.unique' => 'Ce numéro est déjà utilisé par un compte non archivé.',
            'roles.min' => 'Attribuez au moins un rôle au compte.',
        ];
    }
}
