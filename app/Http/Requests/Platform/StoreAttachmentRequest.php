<?php

namespace App\Http\Requests\Platform;

use App\Services\Platform\SettingsService;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;

class StoreAttachmentRequest extends FormRequest
{
    /** @var array<string, array{setting: string, extension: string}> */
    private const MIME_FORMATS = [
        'application/pdf' => ['setting' => 'pdf', 'extension' => 'pdf'],
        'image/jpeg' => ['setting' => 'jpeg', 'extension' => 'jpg'],
        'image/png' => ['setting' => 'png', 'extension' => 'png'],
        'image/webp' => ['setting' => 'webp', 'extension' => 'webp'],
        'image/heic' => ['setting' => 'heic', 'extension' => 'heic'],
        'image/heif' => ['setting' => 'heic', 'extension' => 'heic'],
    ];

    private ?string $detectedMimeType = null;

    private ?string $detectedExtension = null;

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
            'attachable_type' => ['required', Rule::in(['person'])],
            'attachable_id' => ['required', 'integer', 'exists:people,id'],
            'file' => ['required', 'file'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $file = $this->file('file');

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return;
            }

            $settings = app(SettingsService::class);
            $maximumSize = $settings->attachmentMaxSizeBytes();
            $actualSize = $file->getSize();

            if (is_int($actualSize) && $actualSize > $maximumSize) {
                $validator->errors()->add(
                    'file',
                    sprintf(
                        'Ce fichier fait %s Mo, la limite est de %s Mo. Choisissez un fichier plus léger.',
                        $this->megabytes($actualSize),
                        $this->megabytes($maximumSize),
                    ),
                );

                return;
            }

            $realPath = $file->getRealPath();

            if (! is_string($realPath)) {
                $validator->errors()->add('file', 'Ce fichier ne peut pas être lu. Choisissez un autre fichier.');

                return;
            }

            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($realPath);
            $format = is_string($mimeType) ? (self::MIME_FORMATS[$mimeType] ?? null) : null;

            if ($format === null || ! in_array($format['setting'], $settings->attachmentAllowedTypes(), true)) {
                $validator->errors()->add(
                    'file',
                    'Ce type de fichier n’est pas accepté. Choisissez un PDF ou une image autorisée.',
                );

                return;
            }

            $this->detectedMimeType = $mimeType;
            $this->detectedExtension = $format['extension'];
        }];
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            throw new RuntimeException('Le fichier validé est absent.');
        }

        return $file;
    }

    public function actualMimeType(): string
    {
        return $this->detectedMimeType ?? throw new RuntimeException('Le type MIME validé est absent.');
    }

    public function normalizedExtension(): string
    {
        return $this->detectedExtension ?? throw new RuntimeException("L'extension validée est absente.");
    }

    public function attachableId(): int
    {
        return (int) $this->validated('attachable_id');
    }

    private function megabytes(int $bytes): string
    {
        $value = round($bytes / 1024 / 1024, 1);

        return rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attachable_type.required' => 'Indiquez à quelle fiche rattacher ce fichier.',
            'attachable_type.in' => 'Ce type de rattachement n’est pas disponible.',
            'attachable_id.required' => 'Indiquez à quelle fiche rattacher ce fichier.',
            'attachable_id.exists' => 'La fiche choisie n’existe plus.',
            'file.required' => 'Choisissez un fichier.',
            'file.file' => 'Le fichier reçu n’est pas valide. Choisissez-le à nouveau.',
            'file.uploaded' => 'Le téléversement a échoué. Vérifiez la taille du fichier et réessayez.',
        ];
    }
}
