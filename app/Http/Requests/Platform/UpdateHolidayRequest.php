<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $holiday = $this->route('holiday');

        return $holiday instanceof Holiday
            && ($this->user()?->can('update', $holiday) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $holiday = $this->route('holiday');

        return [
            'label' => ['required', 'string', 'max:160'],
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('holidays', 'date')->ignore($holiday instanceof Holiday ? $holiday->getKey() : null),
            ],
            'calendar_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'calendar_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['date.unique' => 'Un autre jour férié existe déjà à cette date.'];
    }
}
