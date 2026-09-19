<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\Internship;
use Illuminate\Foundation\Http\FormRequest;

class StoreTutorSupportSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewCapacity', Internship::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'weekday.required' => 'Choisissez le jour du créneau.',
            'start_time.required' => 'Indiquez l’heure du créneau.',
            'start_time.date_format' => 'Indiquez l’heure sous la forme 14:30.',
        ];
    }
}
