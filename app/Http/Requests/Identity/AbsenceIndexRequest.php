<?php

namespace App\Http\Requests\Identity;

use App\Models\Identity\Absence;
use Illuminate\Foundation\Http\FormRequest;

class AbsenceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Absence::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
