<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\InternshipIntakeForm;
use Illuminate\Foundation\Http\FormRequest;

class DecideInternshipIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('internshipIntakeForm');

        return $form instanceof InternshipIntakeForm && ($this->user()?->can('decide', $form) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'decision_reason' => ['nullable', 'string', 'max:5000', 'required_if:approved,false'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'approved.required' => 'Indiquez votre décision.',
            'decision_reason.required_if' => 'Indiquez pourquoi la fiche est refusée.',
        ];
    }
}
