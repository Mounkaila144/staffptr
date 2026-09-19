<?php

namespace Tests\Feature\Http;

use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IdentityTestCase;

class PrivateAttachmentTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Storage::fake('private');
    }

    public function test_ac_1_upload_uses_an_immutable_private_path_and_has_no_public_url(): void
    {
        $direction = $this->userWithRole('direction');
        $file = UploadedFile::fake()->createWithContent('../../public/preuve-client.pdf', $this->pdf());

        $this->actingAs($direction)
            ->postJson(route('attachments.store'), [
                'attachable_type' => 'person',
                'attachable_id' => $direction->person_id,
                'file' => $file,
            ])
            ->assertCreated();

        $attachment = Attachment::query()->sole();
        Storage::disk('private')->assertExists($attachment->path);
        $this->assertMatchesRegularExpression(
            '#\Aidentity/person/[0-9]{4}/[0-9]{2}/[0-9A-HJKMNP-TV-Z]{26}\.pdf\z#',
            $attachment->path,
        );
        $this->assertSame('preuve-client.pdf', $attachment->original_name);
        $this->assertStringNotContainsString('preuve-client', $attachment->path);
        $publicAttempt = $this->get('/storage/app/private/'.$attachment->path);
        $this->assertContains($publicAttempt->status(), [403, 404]);
    }

    public function test_ac_2_authorized_read_uses_x_sendfile_when_trusted_and_falls_back_otherwise(): void
    {
        $direction = $this->userWithRole('direction');
        $attachment = $this->storedAttachment($direction->person, $direction);

        config()->set('attachments.x_sendfile.enabled', false);
        $fallback = $this->actingAs($direction)->get(route('attachments.show', $attachment));
        $fallback->assertOk()->assertDownload('preuve.pdf');
        $this->assertSame($this->pdf(), $fallback->baseResponse->getFile()->getContent());

        config()->set('attachments.x_sendfile.enabled', true);
        $this->actingAs($direction)
            ->withHeader('X-Sendfile-Type', 'X-Sendfile')
            ->get(route('attachments.show', $attachment))
            ->assertOk()
            ->assertHeader('X-Sendfile', Storage::disk('private')->path($attachment->path));
    }

    public function test_ac_3_and_5_forged_executable_renamed_as_pdf_is_rejected_server_side(): void
    {
        $direction = $this->userWithRole('direction');
        $forged = UploadedFile::fake()->createWithContent('preuve.pdf', "#!/bin/sh\nexit 0\n");

        $this->actingAs($direction)
            ->postJson(route('attachments.store'), [
                'attachable_type' => 'person',
                'attachable_id' => $direction->person_id,
                'file' => $forged,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_ac_3_allowed_types_are_read_from_settings(): void
    {
        $direction = $this->userWithRole('direction');
        $this->setSetting('attachment_allowed_types', ['png']);

        $this->actingAs($direction)
            ->postJson(route('attachments.store'), [
                'attachable_type' => 'person',
                'attachable_id' => $direction->person_id,
                'file' => UploadedFile::fake()->createWithContent('preuve.pdf', $this->pdf()),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_ac_4_size_error_uses_the_current_configured_limit(): void
    {
        $direction = $this->userWithRole('direction');
        $this->setSetting('attachment_max_size_bytes', 5 * 1024 * 1024);
        $largeFile = UploadedFile::fake()->createWithContent(
            'preuve.pdf',
            '%PDF-1.4 '.str_repeat('0', (8 * 1024 * 1024) - 9),
        );

        $this->actingAs($direction)
            ->postJson(route('attachments.store'), [
                'attachable_type' => 'person',
                'attachable_id' => $direction->person_id,
                'file' => $largeFile,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.file.0',
                'Ce fichier fait 8 Mo, la limite est de 5 Mo. Choisissez un fichier plus léger.',
            );
    }

    public function test_ac_7_attachment_inherits_person_visibility(): void
    {
        $target = $this->userWithRole('employe');
        $peer = $this->userWithRole('employe');
        $direction = $this->userWithRole('direction');
        $attachment = $this->storedAttachment($target->person, $target);

        $this->actingAs($peer)->get(route('attachments.show', $attachment))->assertForbidden();
        session()->flush();
        Auth::forgetGuards();
        $this->actingAs($direction)->get(route('attachments.show', $attachment))->assertOk();
    }

    public function test_business_roles_have_attachment_permissions_but_super_admin_does_not(): void
    {
        foreach (['direction', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertTrue($user->can('piece_jointe.consulter'));
            $this->assertTrue($user->can('piece_jointe.creer'));
        }

        $superAdmin = $this->userWithRole('super_admin');
        $this->assertFalse($superAdmin->can('piece_jointe.consulter'));
        $this->assertFalse($superAdmin->can('piece_jointe.creer'));
        $this->actingAs($superAdmin)
            ->postJson(route('attachments.store'), [])
            ->assertForbidden();
    }

    private function storedAttachment(Person $person, User $uploader): Attachment
    {
        $attachment = Attachment::factory()->for($person, 'attachable')->create([
            'uploaded_by' => $uploader->getKey(),
            'original_name' => 'preuve.pdf',
        ]);
        Storage::disk('private')->put($attachment->path, $this->pdf());

        return $attachment;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test pièces jointes privées');

        return $user->fresh('person');
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";
    }
}
