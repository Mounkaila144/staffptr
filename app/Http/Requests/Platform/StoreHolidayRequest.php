<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Holiday::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:160'],
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')],
            'calendar_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'calendar_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['date.unique' => 'Un jour férié existe déjà à cette date. Modifiez-le dans la liste.'];
    }
}
