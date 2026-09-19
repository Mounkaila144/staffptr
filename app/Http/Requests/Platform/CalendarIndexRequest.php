<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\Holiday;
use Illuminate\Foundation\Http\FormRequest;

class CalendarIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Holiday::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
