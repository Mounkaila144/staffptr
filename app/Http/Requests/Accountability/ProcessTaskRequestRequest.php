<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\TaskRequest;
use Illuminate\Foundation\Http\FormRequest;

class ProcessTaskRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $taskRequest = $this->route('taskRequest');

        return $taskRequest instanceof TaskRequest && ($this->user()?->can('process', $taskRequest) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
