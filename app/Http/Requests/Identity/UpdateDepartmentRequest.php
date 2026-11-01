<?php

namespace App\Http\Requests\Identity;

use App\Models\Identity\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && ($this->user()?->can('update', $department) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('departments', 'name')->ignore($this->route('department'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Un service porte déjà ce nom.',
        ];
    }
}
