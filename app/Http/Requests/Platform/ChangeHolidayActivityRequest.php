<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\Holiday;
use Illuminate\Foundation\Http\FormRequest;

class ChangeHolidayActivityRequest extends FormRequest
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
        return [
            'calendar_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'calendar_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
