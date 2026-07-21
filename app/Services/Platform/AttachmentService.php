<?php

namespace App\Services\Platform;

use App\Jobs\Platform\GenerateAttachmentThumbnail;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Support\Auditing\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AttachmentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PrivateImageProcessor $imageProcessor,
    ) {}

    public function store(
        UploadedFile $file,
        Model $attachable,
        User $actor,
        string $actualMimeType,
        string $extension,
    ): Attachment {
        $ulid = (string) Str::ulid();
        $disk = $this->disk();
        $contents = $this->fileContents($file);
        $storedMimeType = $actualMimeType;
        $storedExtension = $extension;

        if (str_starts_with($actualMimeType, 'image/')) {
            $normalized = $this->imageProcessor->normalize($file->getRealPath(), $actualMimeType);
            $contents = $normalized['contents'];
            $storedMimeType = $normalized['mime_type'];
            $storedExtension = $normalized['extension'];
        }

        $path = $this->storagePrefix($attachable).'/'.now('UTC')->format('Y/m')."/{$ulid}.{$storedExtension}";

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException("Le fichier n'a pas pu être enregistré. Réessayez.");
        }

        $attachment = new Attachment;
        $attachment->fill([
            'ulid' => $ulid,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $storedMimeType,
            'extension' => $storedExtension,
            'size_bytes' => strlen($contents),
            'uploaded_by' => $actor->getKey(),
        ]);

        try {
            DB::connection($attachment->getConnectionName())->transaction(function () use ($attachment, $actor): void {
                $this->auditLogger->runExplicitly(
                    auditable: $attachment,
                    operation: fn (): bool => $attachment->saveOrFail(),
                    actorId: $actor->getKey(),
                    actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                    action: 'attachment_uploaded',
                    newValues: [
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'extension' => $attachment->extension,
                        'size_bytes' => $attachment->size_bytes,
                        'attachable_type' => $attachment->getAttribute('attachable_type'),
                        'attachable_id' => $attachment->getAttribute('attachable_id'),
                        'uploaded_by' => $attachment->getAttribute('uploaded_by'),
                    ],
                );
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        if ($attachment->isImage()) {
            GenerateAttachmentThumbnail::dispatch($attachment->getKey());
        }

        return $attachment;
    }

    public function attachExisting(Attachment $attachment, Model $attachable, User $actor): Attachment
    {
        return DB::connection($attachment->getConnectionName())->transaction(function () use (
            $attachment,
            $attachable,
            $actor,
        ): Attachment {
            $lockedAttachment = Attachment::query()
                ->whereKey($attachment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $oldValues = [
                'attachable_type' => $lockedAttachment->getAttribute('attachable_type'),
                'attachable_id' => $lockedAttachment->getAttribute('attachable_id'),
            ];
            $lockedAttachment->fill([
                'attachable_type' => $attachable->getMorphClass(),
                'attachable_id' => $attachable->getKey(),
            ]);

            $this->auditLogger->runExplicitly(
                auditable: $lockedAttachment,
                operation: fn (): bool => $lockedAttachment->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}",
                action: 'attachment_attached',
                oldValues: $oldValues,
                newValues: [
                    'attachable_type' => $lockedAttachment->getAttribute('attachable_type'),
                    'attachable_id' => $lockedAttachment->getAttribute('attachable_id'),
                ],
            );

            return $lockedAttachment->refresh();
        });
    }

    public function thumbnailUrl(Attachment $attachment, bool $refresh = true): ?string
    {
        if ($refresh) {
            $attachment->refresh();
        }

        if ($attachment->thumbnail_path === null) {
            return null;
        }

        $minutes = config('attachments.signed_thumbnail_minutes');

        if (! is_int($minutes)) {
            throw new RuntimeException('La durée de signature des vignettes est invalide.');
        }

        return URL::temporarySignedRoute(
            'attachments.thumbnail',
            now()->addMinutes($minutes),
            ['attachment' => $attachment],
        );
    }

    private function disk(): string
    {
        $disk = config('attachments.disk');

        return is_string($disk) && $disk !== ''
            ? $disk
            : throw new RuntimeException('Le disque privé des pièces jointes est invalide.');
    }

    private function storagePrefix(Model $attachable): string
    {
        $parts = explode('\\', $attachable::class);

        if (count($parts) < 4 || $parts[0] !== 'App' || $parts[1] !== 'Models') {
            throw new RuntimeException('Le module de la pièce jointe ne peut pas être déterminé.');
        }

        return Str::kebab($parts[2]).'/'.Str::kebab($parts[array_key_last($parts)]);
    }

    private function fileContents(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        return is_string($contents)
            ? $contents
            : throw new RuntimeException("Le fichier n'a pas pu être lu.");
    }
}
