<?php

namespace App\Http\Requests\Work;

use App\Enums\ObjectiveState;
use App\Models\Work\Objective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective && ($this->user()?->can('update', $objective) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['state' => ['required', Rule::enum(ObjectiveState::class)], 'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')]];
    }

    public function state(): ObjectiveState
    {
        return ObjectiveState::from($this->string('state')->toString());
    }
}
