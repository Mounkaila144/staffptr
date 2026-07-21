<?php

namespace Tests\Feature\Http;

use App\Enums\DocumentType;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class PersonDocumentHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Storage::fake('private');
    }

    public function test_ac_1_every_document_type_can_be_deposited_for_a_person_with_a_private_attachment(): void
    {
        $direction = $this->userWithRole('direction');
        $target = $this->userWithRole('employe');

        foreach (DocumentType::cases() as $type) {
            $attachment = $this->stagedAttachment($target, $direction, "{$type->value}.pdf");

            $this->actingAs($direction)
                ->post(route('people.documents.store', $target->person), [
                    'document_type' => $type->value,
                    'attachment_ulid' => $attachment->ulid,
                ])
                ->assertRedirect(route('people.documents.index', $target->person));
        }

        $storedTypes = PersonDocument::query()
            ->orderBy('id')
            ->get()
            ->map(static fn (PersonDocument $document): string => $document->document_type->value)
            ->all();
        $this->assertSame(DocumentType::values(), $storedTypes);
        $this->assertDatabaseCount('person_documents', 4);

        foreach (PersonDocument::query()->with('attachment')->get() as $document) {
            $this->assertNotNull($document->attachment);
            $this->assertSame(PersonDocument::class, $document->attachment->getAttribute('attachable_type'));
            Storage::disk('private')->assertExists($document->attachment->path);
        }
    }

    public function test_ac_2_only_subject_manager_and_direction_access_document_and_download_urls(): void
    {
        $manager = $this->userWithRole('tuteur');
        $target = $this->userWithRole('employe', ['manager_id' => $manager->getKey()]);
        $peer = $this->userWithRole('employe');
        $direction = $this->userWithRole('direction');
        $superAdmin = $this->userWithRole('super_admin');
        [$document, $attachment] = $this->storedDocument($target);
        $documentUrl = route('people.documents.show', [$target->person, $document]);
        $fileUrl = route('attachments.show', $attachment);

        foreach ([$target, $manager, $direction] as $authorized) {
            $this->resetAuthentication();
            $this->actingAs($authorized)->get($documentUrl)->assertOk()->assertDownload('contrat.pdf');
        }

        foreach ([$peer, $superAdmin] as $forbidden) {
            $this->resetAuthentication();
            $this->actingAs($forbidden)->get($documentUrl)->assertForbidden();
            $this->actingAs($forbidden)->get($fileUrl)->assertForbidden();
        }

        $this->resetAuthentication();
        $this->actingAs($target)->get($fileUrl)->assertNotFound();
    }

    public function test_ac_5_empty_state_and_deposit_action_follow_the_server_permission(): void
    {
        $target = $this->userWithRole('employe');
        $direction = $this->userWithRole('direction');

        $this->actingAs($target)
            ->get(route('people.documents.index', $target->person))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Identity/People/Documents/Index')
                ->where('documents', [])
                ->where('canDeposit', false)
                ->where('person.id', $target->person_id));

        $this->resetAuthentication();
        $this->actingAs($direction)
            ->get(route('people.documents.index', $target->person))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('documents', [])
                ->where('canDeposit', true));

        $source = (string) file_get_contents(resource_path('js/Pages/Identity/People/Documents/Index.vue'));
        $this->assertStringContainsString('Aucun document dans ce dossier.', $source);
        $this->assertStringContainsString(":action-label=\"canDeposit ? 'Ranger un document' : ''\"", $source);
        $this->assertStringContainsString('<AttachmentUploader', $source);
        $this->assertStringContainsString('document.archived', $source);
        $this->assertStringContainsString('Motif de l’archivage', $source);
    }

    public function test_only_direction_can_deposit_and_archive_through_direct_urls(): void
    {
        $target = $this->userWithRole('employe');
        $employee = $this->userWithRole('employe');
        [$document] = $this->storedDocument($target);

        $this->actingAs($employee)
            ->post(route('people.documents.store', $target->person), [])
            ->assertForbidden();
        $this->actingAs($employee)
            ->patch(route('people.documents.archive', [$target->person, $document]), [
                'archive_reason' => 'Document remplacé.',
            ])
            ->assertForbidden();
    }

    /** @return array{PersonDocument, Attachment} */
    private function storedDocument(User $target): array
    {
        $document = PersonDocument::factory()->for($target->person)->create([
            'uploaded_by' => $target->getKey(),
        ]);
        $attachment = Attachment::factory()->for($document, 'attachable')->create([
            'uploaded_by' => $target->getKey(),
            'original_name' => 'contrat.pdf',
        ]);
        Storage::disk('private')->put($attachment->path, "%PDF-1.4\n%%EOF");

        return [$document, $attachment];
    }

    private function stagedAttachment(User $target, User $uploader, string $name): Attachment
    {
        $attachment = Attachment::factory()->for($target->person, 'attachable')->create([
            'uploaded_by' => $uploader->getKey(),
            'original_name' => $name,
        ]);
        Storage::disk('private')->put($attachment->path, "%PDF-1.4\n%%EOF");

        return $attachment;
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->active()->create($attributes);
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP documents personnels');

        return $user->fresh('person');
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
