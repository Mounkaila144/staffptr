<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Objective;
use Illuminate\Foundation\Http\FormRequest;

class ValidateObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective && ($this->user()?->can('validate', $objective) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
