<?php

namespace App\Http\Requests\Identity;

use App\Enums\AbsenceType;
use App\Models\Identity\Absence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Absence::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AbsenceType::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
            'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')],
        ];
    }

    public function type(): AbsenceType
    {
        return AbsenceType::from($this->string('type')->toString());
    }
}
