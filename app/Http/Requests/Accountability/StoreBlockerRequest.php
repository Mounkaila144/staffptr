<?php

namespace App\Http\Requests\Accountability;

use App\Enums\BlockerUrgency;
use App\Models\Accountability\Blocker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBlockerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Blocker::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'origin_type' => ['required', Rule::in(['task', 'objective', 'daily_report'])],
            'origin_id' => ['required', 'integer', 'min:1'],
            'problem' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', Rule::enum(BlockerUrgency::class)],
            'solicited_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'reported_on' => ['required', 'date_format:Y-m-d'],
            'deadline_impact' => ['required', 'string', 'max:2000'],
            'attempted_action' => ['required', 'string', 'max:5000'],
        ];
    }
}
