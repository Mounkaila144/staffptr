<?php

namespace App\Services\Identity;

use App\Enums\DocumentType;
use App\Models\Identity\Person;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Platform\AttachmentService;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonDocumentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AttachmentService $attachmentService,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forPerson(Person $person, User $actor): array
    {
        return $person->documents()
            ->visibleTo($actor)
            ->with(['uploadedBy.person', 'attachment'])
            ->latest('created_at')
            ->get()
            ->map(function (PersonDocument $document): array {
                $attachment = $document->attachment;

                return [
                    'id' => $document->getKey(),
                    'type' => $document->document_type->value,
                    'type_label' => $document->document_type->label(),
                    'created_at' => DateTimeFormatter::format($document->created_at),
                    'author' => $document->uploadedBy->person->full_name,
                    'archived' => $document->isArchived(),
                    'archived_at' => $document->archived_at === null
                        ? null
                        : DateTimeFormatter::format($document->archived_at),
                    'archive_reason' => $document->archive_reason,
                    'original_name' => $attachment?->original_name,
                    'thumbnail_url' => $attachment instanceof Attachment
                        ? $this->attachmentService->thumbnailUrl($attachment, refresh: false)
                        : null,
                ];
            })
            ->all();
    }

    public function deposit(
        Person $person,
        Attachment $attachment,
        DocumentType $documentType,
        User $actor,
    ): PersonDocument {
        $document = new PersonDocument;

        return DB::connection($document->getConnectionName())->transaction(function () use (
            $person,
            $attachment,
            $documentType,
            $actor,
            $document,
        ): PersonDocument {
            $lockedAttachment = Attachment::query()
                ->whereKey($attachment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertStagedForPerson($lockedAttachment, $person, $actor);

            $document->fill([
                'person_id' => $person->getKey(),
                'document_type' => $documentType,
                'uploaded_by' => $actor->getKey(),
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $document,
                operation: fn (): bool => $document->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'person_document_deposited',
                newValues: [
                    'person_id' => $person->getKey(),
                    'document_type' => $documentType->value,
                    'attachment_ulid' => $lockedAttachment->ulid,
                    'uploaded_by' => $actor->getKey(),
                ],
            );
            $this->attachmentService->attachExisting($lockedAttachment, $document, $actor);

            return $document->load(['attachment', 'uploadedBy.person']);
        });
    }

    public function archive(PersonDocument $document, string $reason, User $actor): PersonDocument
    {
        return DB::connection($document->getConnectionName())->transaction(function () use (
            $document,
            $reason,
            $actor,
        ): PersonDocument {
            $lockedDocument = PersonDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDocument->isArchived()) {
                throw ValidationException::withMessages([
                    'archive_reason' => 'Ce document est déjà archivé et reste consultable.',
                ]);
            }

            $lockedDocument->fill([
                'archived_at' => CarbonImmutable::now('UTC'),
                'archive_reason' => $reason,
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $lockedDocument,
                operation: fn (): bool => $lockedDocument->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'person_document_archived',
                oldValues: ['archived_at' => null, 'archive_reason' => null],
                newValues: [
                    'archived_at' => $lockedDocument->archived_at?->format('Y-m-d H:i:s.v'),
                    'archive_reason' => $reason,
                ],
            );

            return $lockedDocument->refresh();
        });
    }

    public function recordConsultation(PersonDocument $document, User $actor): void
    {
        DB::connection($document->getConnectionName())->transaction(function () use ($document, $actor): void {
            $this->auditLogger->record(
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                auditable: $document,
                action: 'person_document_viewed',
                newValues: [
                    'person_id' => $document->getAttribute('person_id'),
                    'document_type' => $document->document_type->value,
                    'attachment_ulid' => $document->attachment?->ulid,
                    'archived_at' => $document->archived_at?->format('Y-m-d H:i:s.v'),
                ],
            );
        });
    }

    private function assertStagedForPerson(Attachment $attachment, Person $person, User $actor): void
    {
        if ($attachment->getAttribute('attachable_type') !== $person->getMorphClass()
            || (int) $attachment->getAttribute('attachable_id') !== (int) $person->getKey()
            || (int) $attachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages([
                'attachment_ulid' => 'Ce fichier ne peut pas être rangé dans ce dossier.',
            ]);
        }
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
