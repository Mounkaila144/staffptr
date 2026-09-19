<?php

namespace App\Http\Requests\Work;

use App\Enums\ProjectStatus;
use App\Models\Work\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'client_name' => ['nullable', 'string', 'max:160'], 'manager_id' => ['required', 'integer', Rule::exists('users', 'id')], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'status' => ['required', Rule::enum(ProjectStatus::class)], 'planned_budget_xof' => ['nullable', 'integer', 'min:0'], 'spent_budget_xof' => ['nullable', 'integer', 'min:0'], 'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')]];
    }
}
