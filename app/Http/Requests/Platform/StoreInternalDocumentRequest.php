<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInternalDocumentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'requires_acknowledgement' => ['required', 'boolean'],
            'body' => ['nullable', 'string', 'max:200000'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'attachment_ulid' => ['nullable', 'string', 'exists:attachments,ulid'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $body = $this->input('body');
            $attachmentUlid = $this->input('attachment_ulid');

            if ((! is_string($body) || trim($body) === '')
                && (! is_string($attachmentUlid) || trim($attachmentUlid) === '')) {
                $validator->errors()->add(
                    'body',
                    'Ajoutez le contenu du document ou choisissez un fichier.',
                );
            }
        }];
    }

    /** @return array{title: string, requires_acknowledgement: bool, body: string|null, effective_date: string} */
    public function publicationAttributes(): array
    {
        $body = $this->validated('body');

        return [
            'title' => trim((string) $this->validated('title')),
            'requires_acknowledgement' => (bool) $this->validated('requires_acknowledgement'),
            'body' => is_string($body) && trim($body) !== '' ? trim($body) : null,
            'effective_date' => (string) $this->validated('effective_date'),
        ];
    }

    public function attachmentUlid(): ?string
    {
        $ulid = $this->validated('attachment_ulid');

        return is_string($ulid) && $ulid !== '' ? $ulid : null;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Donnez un titre au document.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'requires_acknowledgement.required' => "Indiquez si un accusé d'acceptation est requis.",
            'requires_acknowledgement.boolean' => "Le choix d'accusé d'acceptation est invalide.",
            'body.max' => 'Le contenu est trop long pour être publié.',
            'effective_date.required' => "Indiquez la date d'application.",
            'effective_date.date_format' => "La date d'application doit être une date valide.",
            'attachment_ulid.exists' => "Le fichier choisi n'existe plus. Téléversez-le à nouveau.",
        ];
    }
}
