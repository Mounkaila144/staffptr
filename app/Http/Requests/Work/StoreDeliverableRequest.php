<?php

namespace App\Http\Requests\Work;

use App\Enums\DeliverableStatus;
use App\Models\Work\Deliverable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Deliverable::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['project_id' => ['required', 'integer', Rule::exists('projects', 'id')], 'owner_id' => ['required', 'integer', Rule::exists('users', 'id')], 'title' => ['required', 'string', 'max:200'], 'planned_date' => ['required', 'date_format:Y-m-d'], 'actual_date' => ['nullable', 'date_format:Y-m-d'], 'status' => ['required', Rule::enum(DeliverableStatus::class)]];
    }
}
