<?php

namespace App\Http\Requests\Work;

use App\Enums\ProjectStatus;
use App\Models\Work\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Project::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['status' => ['nullable', Rule::enum(ProjectStatus::class)]];
    }
}
