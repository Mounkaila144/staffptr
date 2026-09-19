<?php

namespace App\Http\Requests\Work;

use App\Enums\WorkTaskStatus;
use App\Models\Work\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Task::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')], 'due_date' => ['nullable', 'date_format:Y-m-d'], 'status' => ['nullable', Rule::enum(WorkTaskStatus::class)], 'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')]];
    }
}
