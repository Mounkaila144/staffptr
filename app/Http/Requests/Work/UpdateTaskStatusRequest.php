<?php

namespace App\Http\Requests\Work;

use App\Enums\WorkTaskStatus;
use App\Models\Work\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && ($this->user()?->can('update', $task) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(WorkTaskStatus::class)], 'reason' => ['nullable', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['status.required' => 'Choisissez le nouveau statut de la tâche.', 'status.enum' => 'Ce statut de tâche n’existe pas.'];
    }

    public function status(): WorkTaskStatus
    {
        return WorkTaskStatus::from($this->string('status')->toString());
    }
}
