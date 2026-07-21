<?php

namespace App\Http\Requests\Identity;

use App\Enums\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonDocumentRequest extends FormRequest
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
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'attachment_ulid' => ['required', 'string', 'size:26', 'exists:attachments,ulid'],
        ];
    }

    public function documentType(): DocumentType
    {
        return DocumentType::from((string) $this->validated('document_type'));
    }

    public function attachmentUlid(): string
    {
        return (string) $this->validated('attachment_ulid');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document_type.required' => 'Choisissez le type de document.',
            'document_type.enum' => "Ce type de document n'est pas disponible.",
            'attachment_ulid.required' => 'Ajoutez le fichier à ranger dans le dossier.',
            'attachment_ulid.size' => "La référence du fichier n'est pas valide. Téléversez-le à nouveau.",
            'attachment_ulid.exists' => "Le fichier téléversé n'est plus disponible. Téléversez-le à nouveau.",
        ];
    }
}
