<?php

namespace App\Http\Requests\Identity;

use App\Models\Identity\JobFunction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobFunctionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $jobFunction = $this->route('jobFunction');

        return $jobFunction instanceof JobFunction
            && ($this->user()?->can('update', $jobFunction) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('job_functions', 'name')->ignore($this->route('jobFunction'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Une fonction porte déjà ce nom.',
        ];
    }
}
