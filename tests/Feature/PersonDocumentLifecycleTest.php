<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\AuditLog;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Platform\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Support\IdentityTestCase;

class PersonDocumentLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Storage::fake('private');
    }

    public function test_ac_3_archiving_requires_a_reason_never_deletes_and_keeps_the_document_downloadable(): void
    {
        $direction = $this->direction();
        [$document, $attachment] = $this->documentWithFile($direction);

        $this->actingAs($direction)
            ->patch(route('people.documents.archive', [$direction->person, $document]), [
                'archive_reason' => 'Une nouvelle version signée remplace ce document.',
            ])
            ->assertRedirect(route('people.documents.index', $direction->person));

        $document->refresh();
        $this->assertTrue($document->isArchived());
        $this->assertSame('Une nouvelle version signée remplace ce document.', $document->archive_reason);
        $this->assertDatabaseCount('person_documents', 1);
        $this->assertDatabaseCount('attachments', 1);
        Storage::disk('private')->assertExists($attachment->path);
        $this->actingAs($direction)
            ->get(route('people.documents.show', [$direction->person, $document]))
            ->assertOk()
            ->assertDownload('contrat.pdf');

        $this->expectException(LogicException::class);
        $document->delete();
    }

    public function test_ac_3_archiving_refuses_a_missing_reason_and_a_second_archive(): void
    {
        $direction = $this->direction();
        [$document] = $this->documentWithFile($direction);

        $this->actingAs($direction)
            ->patch(route('people.documents.archive', [$direction->person, $document]), [
                'archive_reason' => '',
            ])
            ->assertSessionHasErrors('archive_reason');

        $document->forceFill([
            'archived_at' => now('UTC'),
            'archive_reason' => 'Archive initiale motivée.',
        ])->saveQuietly();
        $this->actingAs($direction)
            ->patch(route('people.documents.archive', [$direction->person, $document]), [
                'archive_reason' => 'Tentative de modification du motif.',
            ])
            ->assertSessionHasErrors('archive_reason');

        $this->assertSame('Archive initiale motivée.', $document->refresh()->archive_reason);
    }

    public function test_ac_4_deposit_view_and_archive_are_each_audited_with_the_actor_and_values(): void
    {
        $direction = $this->direction();
        $attachment = Attachment::factory()->for($direction->person, 'attachable')->create([
            'uploaded_by' => $direction->getKey(),
            'original_name' => 'engagement.pdf',
        ]);
        Storage::disk('private')->put($attachment->path, "%PDF-1.4\n%%EOF");

        $this->actingAs($direction)
            ->post(route('people.documents.store', $direction->person), [
                'document_type' => DocumentType::EngagementSigne->value,
                'attachment_ulid' => $attachment->ulid,
            ])
            ->assertRedirect();
        $document = PersonDocument::query()->sole();
        $this->actingAs($direction)
            ->get(route('people.documents.show', [$direction->person, $document]))
            ->assertOk();
        $this->actingAs($direction)
            ->patch(route('people.documents.archive', [$direction->person, $document]), [
                'archive_reason' => 'Engagement remplacé après nouvelle signature.',
            ])
            ->assertRedirect();

        foreach (['person_document_deposited', 'person_document_viewed', 'person_document_archived'] as $action) {
            $audit = AuditLog::query()->where([
                'actor_id' => $direction->getKey(),
                'auditable_type' => PersonDocument::class,
                'auditable_id' => $document->getKey(),
                'action' => $action,
            ])->sole();
            $this->assertNotNull($audit->new_values);
        }

        $depositAudit = AuditLog::query()->where('action', 'person_document_deposited')->sole();
        $this->assertSame(DocumentType::EngagementSigne->value, $depositAudit->new_values['document_type']);
        $this->assertSame($attachment->ulid, $depositAudit->new_values['attachment_ulid']);
        $archiveAudit = AuditLog::query()->where('action', 'person_document_archived')->sole();
        $this->assertSame(
            'Engagement remplacé après nouvelle signature.',
            $archiveAudit->new_values['archive_reason'],
        );
    }

    public function test_attachment_service_accepts_any_model_and_derives_module_and_attachable_path(): void
    {
        $direction = $this->direction();
        $document = PersonDocument::factory()->for($direction->person)->create([
            'uploaded_by' => $direction->getKey(),
        ]);
        $attachment = app(AttachmentService::class)->store(
            file: UploadedFile::fake()->createWithContent('contrat.pdf', "%PDF-1.4\n%%EOF"),
            attachable: $document,
            actor: $direction,
            actualMimeType: 'application/pdf',
            extension: 'pdf',
        );

        $this->assertMatchesRegularExpression(
            '#\Aidentity/person-document/[0-9]{4}/[0-9]{2}/[0-9A-HJKMNP-TV-Z]{26}\.pdf\z#',
            $attachment->path,
        );
        $this->assertSame(PersonDocument::class, $attachment->getAttribute('attachable_type'));
        Storage::disk('private')->assertExists($attachment->path);
    }

    /** @return array{PersonDocument, Attachment} */
    private function documentWithFile(User $owner): array
    {
        $document = PersonDocument::factory()->for($owner->person)->create([
            'uploaded_by' => $owner->getKey(),
        ]);
        $attachment = Attachment::factory()->for($document, 'attachable')->create([
            'uploaded_by' => $owner->getKey(),
            'original_name' => 'contrat.pdf',
        ]);
        Storage::disk('private')->put($attachment->path, "%PDF-1.4\n%%EOF");

        return [$document, $attachment];
    }

    private function direction(): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, 'direction', null, 'Test cycle documents personnels');

        return $user->fresh('person');
    }
}
