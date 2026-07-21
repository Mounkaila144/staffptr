<?php

namespace Tests\Feature;

use App\Jobs\Platform\GenerateAttachmentThumbnail;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Platform\AttachmentService;
use App\Services\Platform\PrivateImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Tests\Support\IdentityTestCase;

class PrivateAttachmentProcessingTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Storage::fake('private');
    }

    public function test_ac_6_image_is_reencoded_and_thumbnail_job_is_queued_and_generates_a_small_derivative(): void
    {
        Queue::fake();
        $direction = $this->direction();
        $image = UploadedFile::fake()->image('photo-client.jpg', 1000, 800);
        $original = (string) file_get_contents($image->getRealPath()).'HIDDEN-EXIF-MARKER';
        $image = UploadedFile::fake()->createWithContent('photo-client.jpg', $original);

        $this->actingAs($direction)
            ->postJson(route('attachments.store'), [
                'attachable_type' => 'person',
                'attachable_id' => $direction->person_id,
                'file' => $image,
            ])
            ->assertCreated();

        $attachment = Attachment::query()->sole();
        $stored = Storage::disk('private')->get($attachment->path);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertSame('jpg', $attachment->extension);
        $this->assertStringNotContainsString('HIDDEN-EXIF-MARKER', $stored);
        Queue::assertPushed(
            GenerateAttachmentThumbnail::class,
            fn (GenerateAttachmentThumbnail $job): bool => $job->attachmentId === $attachment->getKey(),
        );

        (new GenerateAttachmentThumbnail($attachment->getKey()))->handle(app(PrivateImageProcessor::class));
        $attachment->refresh();
        $this->assertNotNull($attachment->thumbnail_path);
        Storage::disk('private')->assertExists($attachment->thumbnail_path);
        $dimensions = getimagesizefromstring(Storage::disk('private')->get($attachment->thumbnail_path));
        $this->assertIsArray($dimensions);
        $this->assertLessThanOrEqual(480, $dimensions[0]);
        $this->assertLessThanOrEqual(480, $dimensions[1]);
    }

    public function test_ac_6_signed_thumbnail_url_expires_after_ten_minutes(): void
    {
        $direction = $this->direction();
        $attachment = Attachment::factory()->image()->for($direction->person, 'attachable')->create([
            'uploaded_by' => $direction->getKey(),
            'thumbnail_path' => 'identity/2026/07/thumbnails/test.jpg',
        ]);
        Storage::disk('private')->put($attachment->thumbnail_path, $this->jpeg());
        $url = app(AttachmentService::class)->thumbnailUrl($attachment);

        $this->assertNotNull($url);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_upload_is_audited_with_attachment_context(): void
    {
        Queue::fake();
        $direction = $this->direction();

        $this->actingAs($direction)
            ->postJson(route('attachments.store'), [
                'attachable_type' => 'person',
                'attachable_id' => $direction->person_id,
                'file' => UploadedFile::fake()->createWithContent('preuve.pdf', "%PDF-1.4\n%%EOF"),
            ])
            ->assertCreated();

        $attachment = Attachment::query()->sole();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $direction->getKey(),
            'auditable_type' => Attachment::class,
            'auditable_id' => $attachment->getKey(),
            'action' => 'attachment_uploaded',
        ]);
    }

    public function test_heic_is_converted_to_jpeg_when_imagick_has_libheif_support(): void
    {
        if (! extension_loaded('imagick') || ! in_array('HEIC', Imagick::queryFormats('HEIC'), true)) {
            $this->markTestSkipped('Imagick avec libheif est requis pour la preuve HEIC.');
        }

        Queue::fake();
        $direction = $this->direction();
        $imagick = new Imagick;
        $imagick->newImage(20, 20, 'white');
        $imagick->setImageFormat('heic');
        $heic = UploadedFile::fake()->createWithContent('photo.heic', $imagick->getImagesBlob());

        $this->actingAs($direction)
            ->postJson(route('attachments.store'), [
                'attachable_type' => 'person',
                'attachable_id' => $direction->person_id,
                'file' => $heic,
            ])
            ->assertCreated();

        $attachment = Attachment::query()->sole();
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertSame('jpg', $attachment->extension);
    }

    private function direction(): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, 'direction', null, 'Test traitement image');

        return $user->fresh('person');
    }

    private function jpeg(): string
    {
        $file = UploadedFile::fake()->image('thumbnail.jpg', 64, 64);

        return (string) file_get_contents($file->getRealPath());
    }
}
