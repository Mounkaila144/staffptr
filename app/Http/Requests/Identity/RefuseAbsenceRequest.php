<?php

namespace App\Http\Requests\Identity;

use App\Models\Identity\Absence;
use Illuminate\Foundation\Http\FormRequest;

class RefuseAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof Absence && ($this->user()?->can('refuse', $absence) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['decision_reason' => ['required', 'string', 'max:500']];
    }
}
