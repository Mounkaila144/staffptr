<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentThumbnailController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Attachment $attachment): BinaryFileResponse
    {
        $thumbnailPath = $attachment->thumbnail_path;
        abort_unless(is_string($thumbnailPath), 404);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($thumbnailPath), 404);

        return response()->file($disk->path($thumbnailPath), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
