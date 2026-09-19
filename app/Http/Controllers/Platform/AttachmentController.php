<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreAttachmentRequest;
use App\Models\Identity\Person;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Platform\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $attachmentService) {}

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $attachable = Person::query()->findOrFail($request->attachableId());
        Gate::authorize('view', $attachable);
        $attachment = $this->attachmentService->store(
            file: $request->uploadedFile(),
            attachable: $attachable,
            actor: $actor,
            actualMimeType: $request->actualMimeType(),
            extension: $request->normalizedExtension(),
        );

        return response()->json([
            'attachment' => [
                'ulid' => $attachment->ulid,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'thumbnail_url' => $this->attachmentService->thumbnailUrl($attachment),
            ],
        ], 201);
    }

    public function show(Request $request, Attachment $attachment): BinaryFileResponse
    {
        $this->actor($request);
        $attachable = $attachment->attachable()->firstOrFail();
        Gate::authorize('view', $attachable);
        abort_if($attachable instanceof PersonDocument, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        if (config('attachments.x_sendfile.enabled') === true) {
            BinaryFileResponse::trustXSendfileTypeHeader();
        }

        return response()->download(
            $disk->path($attachment->path),
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
