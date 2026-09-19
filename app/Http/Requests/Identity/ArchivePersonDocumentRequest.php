<?php

namespace App\Http\Requests\Identity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ArchivePersonDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'archive_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function archiveReason(): string
    {
        return trim((string) $this->validated('archive_reason'));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archive_reason.required' => 'Expliquez pourquoi ce document doit être archivé.',
            'archive_reason.min' => 'Précisez le motif en au moins 10 caractères.',
            'archive_reason.max' => 'Le motif ne peut pas dépasser 1 000 caractères.',
        ];
    }
}
