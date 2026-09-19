<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Project;
use App\Models\Work\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddWorkLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $item = $this->route('project') ?? $this->route('task');

        return ($item instanceof Project || $item instanceof Task)
            && ($this->user()?->can('update', $item) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048'],
        ];
    }
}
