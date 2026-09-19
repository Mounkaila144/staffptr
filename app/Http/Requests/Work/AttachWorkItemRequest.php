<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Project;
use App\Models\Work\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachWorkItemRequest extends FormRequest
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
        return ['attachment_ulid' => ['required', 'string', 'size:26', Rule::exists('attachments', 'ulid')]];
    }
}
