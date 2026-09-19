<?php

namespace App\Http\Requests\Work;

use App\Enums\ProjectStatus;
use App\Models\Work\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('update', $project) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ProjectStatus::class)], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }

    public function status(): ProjectStatus
    {
        return ProjectStatus::from($this->string('status')->toString());
    }
}
