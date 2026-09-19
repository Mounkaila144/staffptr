<?php

namespace App\Http\Requests\Work;

use App\Enums\WorkPriority;
use App\Enums\WorkTaskStatus;
use App\Models\Work\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')], 'objective_id' => ['nullable', 'integer', Rule::exists('objectives', 'id')], 'parent_id' => ['nullable', 'integer', Rule::exists('work_tasks', 'id')], 'assignee_id' => ['required', 'integer', Rule::exists('users', 'id')], 'title' => ['required', 'string', 'max:200'], 'due_date' => ['required', 'date_format:Y-m-d'], 'priority' => ['required', Rule::enum(WorkPriority::class)], 'status' => ['required', Rule::enum(WorkTaskStatus::class)], 'link_label' => ['nullable', 'string', 'max:120'], 'link_url' => ['nullable', 'url', 'max:2048'], 'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')]];
    }
}
