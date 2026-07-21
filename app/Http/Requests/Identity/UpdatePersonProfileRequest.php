<?php

namespace App\Http\Requests\Identity;

use App\Enums\RelationType;
use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\HierarchyService;
use App\Services\Identity\PersonProfileService;
use App\Support\PhoneNumber;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdatePersonProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $person = $this->route('person');

        return $person instanceof Person
            && ($this->user()?->can('update', $person) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->account();

        return [
            'full_name' => ['required', 'string', 'max:160'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+227[0-9]{8}$/',
                Rule::unique('users', 'phone')
                    ->ignore($account->getKey())
                    ->where(static fn (Builder $query): Builder => $query->where('state', '!=', UserState::Archive->value)),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'job_function_id' => [
                'nullable',
                'integer',
                Rule::exists('job_functions', 'id')->where('is_active', true),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('state', UserState::Actif->value),
            ],
            'relation_type' => ['required', Rule::enum(RelationType::class)],
            'contract_start_date' => ['nullable', 'date_format:Y-m-d'],
            'contract_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:contract_start_date'],
            'photo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2 * 1024)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('manager_id')) {
                return;
            }

            $managerId = $this->input('manager_id');

            try {
                app(HierarchyService::class)->assertManagerAssignmentAllowed(
                    $this->account(),
                    is_numeric($managerId) ? (int) $managerId : null,
                );
            } catch (ValidationException $exception) {
                foreach ($exception->errors()['manager_id'] ?? [] as $message) {
                    $validator->errors()->add('manager_id', $message);
                }
            }
        }];
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
            // La règle de format fournit le message utilisateur attendu.
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => PhoneNumber::INVALID_MESSAGE,
            'phone.unique' => 'Ce numéro est déjà utilisé par un autre compte non archivé.',
            'contract_end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'photo.image' => 'Choisissez une image JPG, PNG ou WebP.',
            'photo.max' => 'La photo ne doit pas dépasser 2 Mo.',
        ];
    }

    private function account(): User
    {
        $person = $this->route('person');

        abort_unless($person instanceof Person, 404);

        return app(PersonProfileService::class)->currentAccount($person);
    }
}
