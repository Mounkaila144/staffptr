<?php

namespace App\Http\Requests\Work;

use App\Enums\ObjectiveState;
use App\Models\Work\Objective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObjectiveIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Objective::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['month' => ['nullable', 'date_format:Y-m'], 'state' => ['nullable', Rule::enum(ObjectiveState::class)], 'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')]];
    }
}
