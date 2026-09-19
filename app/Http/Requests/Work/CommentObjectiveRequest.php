<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Objective;
use Illuminate\Foundation\Http\FormRequest;

class CommentObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective && ($this->user()?->can('comment', $objective) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000'], 'correction_requested' => ['nullable', 'boolean']];
    }
}
