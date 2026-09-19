<?php

namespace Database\Factories\Platform;

use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ulid = (string) Str::ulid();

        return [
            'ulid' => $ulid,
            'attachable_type' => Person::class,
            'attachable_id' => Person::factory(),
            'disk' => 'private',
            'path' => 'identity/person/'.now('UTC')->format('Y/m')."/{$ulid}.pdf",
            'original_name' => 'justificatif.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'thumbnail_path' => null,
            'uploaded_by' => User::factory()->active(),
        ];
    }

    public function image(): static
    {
        return $this->state(function (array $attributes): array {
            $ulid = (string) $attributes['ulid'];

            return [
                'path' => 'identity/person/'.now('UTC')->format('Y/m')."/{$ulid}.jpg",
                'original_name' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
            ];
        });
    }
}
