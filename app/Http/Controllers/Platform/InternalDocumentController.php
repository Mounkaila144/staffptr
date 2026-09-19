<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreInternalDocumentRequest;
use App\Http\Requests\Platform\StoreInternalDocumentVersionRequest;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\InternalDocument;
use App\Services\Platform\InternalDocumentService;
use App\Services\Platform\NotificationReadService;
use App\Services\Platform\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InternalDocumentController extends Controller
{
    public function __construct(
        private readonly InternalDocumentService $internalDocumentService,
        private readonly NotificationReadService $notificationReadService,
        private readonly SettingsService $settingsService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InternalDocument::class);
        $actor = $this->actor($request);

        return Inertia::render('Platform/InternalDocuments/Index', [
            'documents' => $this->internalDocumentService->forIndex($actor),
            'canManage' => Gate::forUser($actor)->allows('create', InternalDocument::class),
            'uploaderPersonId' => (int) $actor->person_id,
            'attachmentAllowedTypes' => $this->settingsService->attachmentAllowedTypes(),
            'attachmentMaxSizeBytes' => $this->settingsService->attachmentMaxSizeBytes(),
        ]);
    }

    public function show(Request $request, InternalDocument $internalDocument): Response
    {
        Gate::authorize('view', $internalDocument);
        $actor = $this->actor($request);
        $this->notificationReadService->markForLink(
            $actor,
            route('internal-documents.show', $internalDocument, absolute: false),
        );

        return Inertia::render('Platform/InternalDocuments/Show', [
            'document' => $this->internalDocumentService->forDisplay($internalDocument, $actor),
            'canManage' => Gate::forUser($actor)->allows('update', $internalDocument),
            'uploaderPersonId' => (int) $actor->person_id,
            'attachmentAllowedTypes' => $this->settingsService->attachmentAllowedTypes(),
            'attachmentMaxSizeBytes' => $this->settingsService->attachmentMaxSizeBytes(),
        ]);
    }

    public function store(StoreInternalDocumentRequest $request): RedirectResponse
    {
        Gate::authorize('create', InternalDocument::class);
        $document = $this->internalDocumentService->publish(
            attributes: $request->publicationAttributes(),
            attachment: $this->attachment($request->attachmentUlid()),
            actor: $this->actor($request),
        );

        return redirect()->route('internal-documents.show', $document);
    }

    public function storeVersion(
        StoreInternalDocumentVersionRequest $request,
        InternalDocument $internalDocument,
    ): RedirectResponse {
        Gate::authorize('update', $internalDocument);
        $this->internalDocumentService->publishVersion(
            document: $internalDocument,
            attributes: $request->publicationAttributes(),
            attachment: $this->attachment($request->attachmentUlid()),
            actor: $this->actor($request),
        );

        return redirect()->route('internal-documents.show', $internalDocument);
    }

    public function acknowledge(Request $request, InternalDocument $internalDocument): RedirectResponse
    {
        Gate::authorize('acknowledge', $internalDocument);
        $this->internalDocumentService->acknowledge($internalDocument, $this->actor($request));

        return redirect()->route('internal-documents.show', $internalDocument);
    }

    public function acknowledgements(Request $request, InternalDocument $internalDocument): Response
    {
        Gate::authorize('viewAcknowledgements', $internalDocument);
        $this->actor($request);

        return Inertia::render('Platform/InternalDocuments/Acknowledgements', [
            ...$this->internalDocumentService->acknowledgementStatus($internalDocument),
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function attachment(?string $ulid): ?Attachment
    {
        return $ulid === null ? null : Attachment::query()->where('ulid', $ulid)->firstOrFail();
    }
}
