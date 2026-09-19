<?php

namespace App\Services\Platform;

use App\Enums\UserState;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\InternalDocument;
use App\Models\Platform\InternalDocumentAcknowledgement;
use App\Models\Platform\InternalDocumentVersion;
use App\Notifications\InternalDocumentPublishedNotification;
use App\Support\Auditing\AuditLogger;
use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use LogicException;

class InternalDocumentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AttachmentService $attachmentService,
    ) {}

    /** @return list<array<string, mixed>> */
    public function forIndex(User $actor): array
    {
        return InternalDocument::query()
            ->with([
                'currentVersion.attachment',
                'currentVersion.acknowledgements' => static function (Relation $relation) use ($actor): void {
                    $relation->getQuery()->where('user_id', $actor->getKey());
                },
            ])
            ->orderBy('title')
            ->get()
            ->map(function (InternalDocument $document): array {
                $version = $document->currentVersion;

                return [
                    'id' => (int) $document->getKey(),
                    'title' => $document->title,
                    'requires_acknowledgement' => $document->requires_acknowledgement,
                    'version_number' => $version?->version_number,
                    'effective_date' => $version?->effective_date->format('d/m/Y'),
                    'published_at' => $version instanceof InternalDocumentVersion
                        ? DateTimeFormatter::format($version->published_at)
                        : null,
                    'acknowledged' => $version?->acknowledgements->isNotEmpty() ?? false,
                    'has_file' => $version?->attachment !== null,
                ];
            })
            ->all();
    }

    /** @return array<string, mixed> */
    public function forDisplay(InternalDocument $document, User $actor): array
    {
        $versions = $document->versions()
            ->with([
                'attachment',
                'publisher.person',
                'acknowledgements' => static function (Relation $relation) use ($actor): void {
                    $relation->getQuery()->where('user_id', $actor->getKey());
                },
            ])
            ->orderByDesc('version_number')
            ->get();
        $current = $versions->firstWhere('id', $document->current_version_id);

        if (! $current instanceof InternalDocumentVersion) {
            throw new LogicException("Le document n'a pas de version courante.");
        }

        return [
            'id' => (int) $document->getKey(),
            'title' => $document->title,
            'requires_acknowledgement' => $document->requires_acknowledgement,
            'current_version_id' => (int) $current->getKey(),
            'current_version_number' => $current->version_number,
            'acknowledged' => $current->acknowledgements->isNotEmpty(),
            'versions' => $versions->map(
                fn (InternalDocumentVersion $version): array => $this->versionForDisplay(
                    $version,
                    (int) $current->getKey(),
                ),
            )->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function acknowledgementStatus(InternalDocument $document): array
    {
        $version = $this->currentVersion($document);
        $version->load('acknowledgements');
        $acknowledgements = $version->acknowledgements->keyBy('user_id');
        $ageDays = (int) floor($version->published_at->diffInDays(CarbonImmutable::now('UTC')));

        return [
            'document' => [
                'id' => (int) $document->getKey(),
                'title' => $document->title,
                'version_number' => $version->version_number,
                'published_at' => DateTimeFormatter::format($version->published_at),
                'age_days' => $ageDays,
            ],
            'users' => $this->audience()
                ->map(function (User $user) use ($acknowledgements, $ageDays): array {
                    $acknowledgement = $acknowledgements->get($user->getKey());

                    return [
                        'id' => (int) $user->getKey(),
                        'name' => $user->person->full_name,
                        'accepted' => $acknowledgement instanceof InternalDocumentAcknowledgement,
                        'accepted_at' => $acknowledgement instanceof InternalDocumentAcknowledgement
                            ? DateTimeFormatter::format($acknowledgement->acknowledged_at)
                            : null,
                        'age_days' => $ageDays,
                    ];
                })
                ->sortBy('name')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{title: string, requires_acknowledgement: bool, body: string|null, effective_date: string}  $attributes
     */
    public function publish(
        array $attributes,
        ?Attachment $attachment,
        User $actor,
    ): InternalDocument {
        $document = new InternalDocument;

        $published = DB::connection($document->getConnectionName())->transaction(function () use (
            $attributes,
            $attachment,
            $actor,
            $document,
        ): InternalDocument {
            $lockedAttachment = $this->lockedStagedAttachment($attachment, $actor);
            $document->fill([
                'title' => $attributes['title'],
                'requires_acknowledgement' => $attributes['requires_acknowledgement'],
                'current_version_id' => null,
            ]);
            $document->saveQuietly();
            $version = $this->createVersion($document, 1, $attributes, $actor);

            if ($lockedAttachment instanceof Attachment) {
                $this->attachmentService->attachExisting($lockedAttachment, $version, $actor);
            }

            $document->current_version_id = $version->getKey();
            $this->auditLogger->runExplicitly(
                auditable: $document,
                operation: fn (): bool => $document->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'internal_document_published',
                oldValues: null,
                newValues: $this->publicationAuditValues($document, $version, $lockedAttachment),
            );

            return $document->refresh();
        });

        $this->notifyAudience($published);

        return $published;
    }

    /**
     * @param  array{title: string, requires_acknowledgement: bool, body: string|null, effective_date: string}  $attributes
     */
    public function publishVersion(
        InternalDocument $document,
        array $attributes,
        ?Attachment $attachment,
        User $actor,
    ): InternalDocument {
        $published = DB::connection($document->getConnectionName())->transaction(function () use (
            $document,
            $attributes,
            $attachment,
            $actor,
        ): InternalDocument {
            $lockedDocument = InternalDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $current = $this->currentVersion($lockedDocument);
            $lockedAttachment = $this->lockedStagedAttachment($attachment, $actor);
            $version = $this->createVersion(
                $lockedDocument,
                $current->version_number + 1,
                $attributes,
                $actor,
            );

            if ($lockedAttachment instanceof Attachment) {
                $this->attachmentService->attachExisting($lockedAttachment, $version, $actor);
            }

            $oldValues = [
                'title' => $lockedDocument->title,
                'requires_acknowledgement' => $lockedDocument->requires_acknowledgement,
                'current_version_id' => $lockedDocument->current_version_id,
                'version_number' => $current->version_number,
            ];
            $lockedDocument->fill([
                'title' => $attributes['title'],
                'requires_acknowledgement' => $attributes['requires_acknowledgement'],
                'current_version_id' => $version->getKey(),
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $lockedDocument,
                operation: fn (): bool => $lockedDocument->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'internal_document_version_published',
                oldValues: $oldValues,
                newValues: $this->publicationAuditValues($lockedDocument, $version, $lockedAttachment),
            );

            return $lockedDocument->refresh();
        });

        $this->notifyAudience($published);

        return $published;
    }

    public function acknowledge(InternalDocument $document, User $actor): InternalDocumentAcknowledgement
    {
        return DB::connection($document->getConnectionName())->transaction(function () use (
            $document,
            $actor,
        ): InternalDocumentAcknowledgement {
            $lockedDocument = InternalDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedDocument->requires_acknowledgement) {
                throw ValidationException::withMessages([
                    'acknowledgement' => "Ce document ne demande pas d'accusé d'acceptation.",
                ]);
            }

            $version = $this->currentVersion($lockedDocument);
            $alreadyAcknowledged = InternalDocumentAcknowledgement::query()
                ->where('internal_document_version_id', $version->getKey())
                ->where('user_id', $actor->getKey())
                ->exists();

            if ($alreadyAcknowledged) {
                throw ValidationException::withMessages([
                    'acknowledgement' => 'Vous avez déjà accepté cette version.',
                ]);
            }

            $acknowledgement = new InternalDocumentAcknowledgement;
            $acknowledgement->fill([
                'internal_document_version_id' => $version->getKey(),
                'user_id' => $actor->getKey(),
                'acknowledged_at' => CarbonImmutable::now('UTC'),
            ]);
            $this->auditLogger->runExplicitly(
                auditable: $acknowledgement,
                operation: fn (): bool => $acknowledgement->saveOrFail(),
                actorId: $actor->getKey(),
                actorLabel: $this->actorLabel($actor),
                action: 'internal_document_acknowledged',
                newValues: [
                    'internal_document_id' => $lockedDocument->getKey(),
                    'internal_document_version_id' => $version->getKey(),
                    'version_number' => $version->version_number,
                    'user_id' => $actor->getKey(),
                    'acknowledged_at' => $acknowledgement->acknowledged_at->format('Y-m-d H:i:s.v'),
                ],
            );

            return $acknowledgement->refresh();
        });
    }

    /**
     * @param  array{title: string, requires_acknowledgement: bool, body: string|null, effective_date: string}  $attributes
     */
    private function createVersion(
        InternalDocument $document,
        int $versionNumber,
        array $attributes,
        User $actor,
    ): InternalDocumentVersion {
        $version = new InternalDocumentVersion;
        $version->fill([
            'internal_document_id' => $document->getKey(),
            'version_number' => $versionNumber,
            'body' => $attributes['body'],
            'effective_date' => $attributes['effective_date'],
            'published_by' => $actor->getKey(),
            'published_at' => CarbonImmutable::now('UTC'),
        ]);
        $version->saveQuietly();

        return $version;
    }

    private function lockedStagedAttachment(?Attachment $attachment, User $actor): ?Attachment
    {
        if (! $attachment instanceof Attachment) {
            return null;
        }

        $lockedAttachment = Attachment::query()
            ->whereKey($attachment->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $person = $actor->person;

        if ($lockedAttachment->getAttribute('attachable_type') !== $person->getMorphClass()
            || (int) $lockedAttachment->getAttribute('attachable_id') !== (int) $person->getKey()
            || (int) $lockedAttachment->getAttribute('uploaded_by') !== (int) $actor->getKey()) {
            throw ValidationException::withMessages([
                'attachment_ulid' => 'Ce fichier ne peut pas être publié avec ce document.',
            ]);
        }

        return $lockedAttachment;
    }

    private function currentVersion(InternalDocument $document): InternalDocumentVersion
    {
        $version = $document->currentVersion()->first();

        return $version instanceof InternalDocumentVersion
            ? $version
            : throw new LogicException("Le document n'a pas de version courante.");
    }

    /** @return Collection<int, User> */
    private function audience(): Collection
    {
        return User::query()
            ->where('state', UserState::Actif)
            ->with(['person', 'roles.permissions', 'permissions'])
            ->get()
            ->filter(static fn (User $user): bool => $user->can('document_interne.consulter'))
            ->values();
    }

    private function notifyAudience(InternalDocument $document): void
    {
        $version = $this->currentVersion($document);
        Notification::send(
            $this->audience(),
            new InternalDocumentPublishedNotification(
                title: $document->title,
                versionNumber: $version->version_number,
                link: route('internal-documents.show', $document, absolute: false),
            ),
        );
    }

    /** @return array<string, mixed> */
    private function versionForDisplay(InternalDocumentVersion $version, int $currentVersionId): array
    {
        return [
            'id' => (int) $version->getKey(),
            'version_number' => $version->version_number,
            'body' => $version->body,
            'effective_date' => $version->effective_date->format('d/m/Y'),
            'published_at' => DateTimeFormatter::format($version->published_at),
            'publisher' => $version->publisher->person->full_name,
            'is_current' => (int) $version->getKey() === $currentVersionId,
            'acknowledged' => $version->acknowledgements->isNotEmpty(),
            'attachment' => $version->attachment instanceof Attachment ? [
                'name' => $version->attachment->original_name,
                'url' => route('attachments.show', $version->attachment),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function publicationAuditValues(
        InternalDocument $document,
        InternalDocumentVersion $version,
        ?Attachment $attachment,
    ): array {
        return [
            'title' => $document->title,
            'requires_acknowledgement' => $document->requires_acknowledgement,
            'current_version_id' => $version->getKey(),
            'version_number' => $version->version_number,
            'effective_date' => $version->effective_date->toDateString(),
            'attachment_ulid' => $attachment?->ulid,
        ];
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}
