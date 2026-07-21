<?php

namespace App\Jobs\Platform;

use App\Models\Platform\Attachment;
use App\Services\Platform\PrivateImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAttachmentThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $attachmentId) {}

    /**
     * Execute the job.
     */
    public function handle(PrivateImageProcessor $imageProcessor): void
    {
        $attachment = Attachment::query()->findOrFail($this->attachmentId);

        if (! $attachment->isImage() || $attachment->thumbnail_path !== null) {
            return;
        }

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            return;
        }

        $thumbnailPath = dirname($attachment->path)."/thumbnails/{$attachment->ulid}.jpg";
        $thumbnail = $imageProcessor->thumbnail($disk->get($attachment->path));

        if (! $disk->put($thumbnailPath, $thumbnail)) {
            return;
        }

        try {
            $attachment->thumbnail_path = $thumbnailPath;
            $attachment->saveQuietly();
        } catch (Throwable $exception) {
            $disk->delete($thumbnailPath);

            throw $exception;
        }
    }
}
