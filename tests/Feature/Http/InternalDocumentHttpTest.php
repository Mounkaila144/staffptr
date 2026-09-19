<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\InternalDocument;
use App\Models\Platform\InternalDocumentVersion;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Platform\InternalDocumentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class InternalDocumentHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Notification::fake();
        Storage::fake('private');
    }

    public function test_ac_1_direction_can_publish_body_and_readers_see_version_and_effective_date(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');

        $this->actingAs($direction)
            ->post(route('internal-documents.store'), [
                'title' => 'Charte interne',
                'requires_acknowledgement' => true,
                'body' => 'Contenu de la charte.',
                'effective_date' => '2026-08-01',
            ])
            ->assertRedirect();
        $document = InternalDocument::query()->sole();

        $this->resetAuthentication();
        $this->actingAs($employee)
            ->get(route('internal-documents.show', $document))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Platform/InternalDocuments/Show')
                ->where('document.title', 'Charte interne')
                ->where('document.current_version_number', 1)
                ->where('document.versions.0.effective_date', '01/08/2026')
                ->where('document.versions.0.body', 'Contenu de la charte.'));
    }

    public function test_ac_1_publication_requires_content_or_file(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->post(route('internal-documents.store'), [
                'title' => 'Document vide',
                'requires_acknowledgement' => true,
                'body' => '   ',
                'effective_date' => '2026-08-01',
            ])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('internal_documents', 0);
    }

    public function test_ac_2_reader_can_acknowledge_current_version_only_once(): void
    {
        [$document] = $this->documentWithCurrentVersion();
        $employee = $this->userWithRole('employe');

        $this->actingAs($employee)
            ->post(route('internal-documents.acknowledge', $document))
            ->assertRedirect(route('internal-documents.show', $document));
        $this->actingAs($employee)
            ->post(route('internal-documents.acknowledge', $document))
            ->assertSessionHasErrors('acknowledgement');

        $this->assertDatabaseCount('internal_document_acknowledgements', 1);
    }

    public function test_ac_4_all_versions_are_visible_and_private_file_download_uses_version_policy(): void
    {
        [$document, $version] = $this->documentWithCurrentVersion(versionNumber: 2);
        $reader = $this->userWithRole('finance');
        $superAdmin = $this->userWithRole('super_admin');
        $older = InternalDocumentVersion::factory()->for($document, 'document')->create([
            'version_number' => 1,
            'body' => 'Ancienne version.',
        ]);
        $attachment = Attachment::factory()->for($version, 'attachable')->create([
            'uploaded_by' => $reader->getKey(),
            'original_name' => 'charte.pdf',
        ]);
        Storage::disk('private')->put($attachment->path, "%PDF-1.4\n%%EOF");

        $this->actingAs($reader)
            ->get(route('internal-documents.show', $document))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->has('document.versions', 2)
                ->where('document.versions.0.version_number', 2)
                ->where('document.versions.1.id', $older->getKey()));
        $this->actingAs($reader)->get(route('attachments.show', $attachment))->assertOk()->assertDownload('charte.pdf');

        $this->resetAuthentication();
        $this->actingAs($superAdmin)->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_ac_5_routes_enforce_business_readers_direction_management_and_super_admin_exclusion(): void
    {
        [$document] = $this->documentWithCurrentVersion();
        $direction = $this->userWithRole('direction');

        foreach (['finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $reader = $this->userWithRole($role);
            $this->resetAuthentication();
            $this->actingAs($reader)->get(route('internal-documents.index'))->assertOk();
            $this->actingAs($reader)->get(route('internal-documents.show', $document))->assertOk();
            $this->actingAs($reader)->get(route('internal-documents.acknowledgements', $document))->assertForbidden();
            $this->actingAs($reader)->post(route('internal-documents.versions.store', $document), [])->assertForbidden();
        }

        $this->resetAuthentication();
        $this->actingAs($direction)->get(route('internal-documents.acknowledgements', $document))->assertOk();
        $superAdmin = $this->userWithRole('super_admin');
        $this->resetAuthentication();
        $this->actingAs($superAdmin)->get(route('internal-documents.index'))->assertForbidden();

        $matrix = config('authorization-matrix');
        $this->assertArrayNotHasKey('testing.authorization.internal-document.view', $matrix['fixtures']);
        foreach ([
            'internal-documents.index',
            'internal-documents.show',
            'internal-documents.store',
            'internal-documents.versions.store',
            'internal-documents.acknowledge',
            'internal-documents.acknowledgements',
        ] as $routeName) {
            $this->assertArrayHasKey($routeName, $matrix['routes']);
        }
    }

    public function test_ac_5_direction_table_distinguishes_accepted_and_pending_users_with_age(): void
    {
        [$document] = $this->documentWithCurrentVersion();
        $direction = $this->userWithRole('direction');
        $accepted = $this->userWithRole('employe');
        $pending = $this->userWithRole('stagiaire');
        app(InternalDocumentService::class)->acknowledge($document, $accepted);

        $this->actingAs($direction)
            ->get(route('internal-documents.acknowledgements', $document))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Platform/InternalDocuments/Acknowledgements')
                ->where('document.id', $document->getKey())
                ->has('users', 3)
                ->where('users.0.age_days', 0));

        $this->assertNotSame($accepted->getKey(), $pending->getKey());
    }

    public function test_ac_7_mobile_reading_keeps_fluid_text_and_acceptance_at_document_footer(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Platform/InternalDocuments/Show.vue'));

        $this->assertStringContainsString('min-w-0 whitespace-pre-wrap break-words text-base leading-7', $source);
        $this->assertStringContainsString('<footer class="grid gap-3 border-t border-separator pt-5">', $source);
        $this->assertStringContainsString('J’ai lu et j’accepte', $source);
        $this->assertStringNotContainsString('overflow-x-auto', $source);
        $this->assertStringContainsString('touch-target', $source);
    }

    /** @return array{InternalDocument, InternalDocumentVersion} */
    private function documentWithCurrentVersion(int $versionNumber = 1): array
    {
        $publisher = User::factory()->active()->create();
        $document = InternalDocument::factory()->create();
        $version = InternalDocumentVersion::factory()->for($document, 'document')->create([
            'version_number' => $versionNumber,
            'published_by' => $publisher->getKey(),
        ]);
        $document->forceFill(['current_version_id' => $version->getKey()])->saveQuietly();

        return [$document, $version];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP bibliothèque interne');

        return $user->fresh('person');
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}
