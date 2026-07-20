<?php

namespace App\Http\Requests\Identity;

use App\Enums\UserState;
use App\Models\Identity\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['nullable', Rule::enum(UserState::class)],
            'role' => ['nullable', 'string', Rule::in(array_keys((array) config('permission-catalog.roles')))],
        ];
    }
}
