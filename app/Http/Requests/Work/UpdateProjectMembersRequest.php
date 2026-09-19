<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('update', $project) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['add', 'remove'])], 'user_id' => ['required', 'integer', Rule::exists('users', 'id')], 'date' => ['required', 'date_format:Y-m-d']];
    }
}
