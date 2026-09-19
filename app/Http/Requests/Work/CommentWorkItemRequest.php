<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Project;
use App\Models\Work\Task;
use Illuminate\Foundation\Http\FormRequest;

class CommentWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('project') ?? $this->route('task');

        return ($item instanceof Project || $item instanceof Task)
            && ($this->user()?->can('update', $item) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }
}
