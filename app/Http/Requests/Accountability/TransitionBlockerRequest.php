<?php

namespace App\Http\Requests\Accountability;

use App\Enums\BlockerState;
use App\Models\Accountability\Blocker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionBlockerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $blocker = $this->route('blocker');

        return $blocker instanceof Blocker && ($this->user()?->can('transition', $blocker) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['state' => ['required', Rule::enum(BlockerState::class)], 'closure_reason' => ['nullable', 'required_if:state,ferme_sans_solution', 'string', 'max:2000']];
    }
}
