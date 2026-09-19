<?php

namespace App\Http\Requests\Identity;

use App\Models\Identity\Absence;
use Illuminate\Foundation\Http\FormRequest;

class ApproveAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof Absence && ($this->user()?->can('approve', $absence) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
