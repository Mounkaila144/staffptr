<?php

namespace App\Http\Controllers\Identity;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ArchivePersonDocumentRequest;
use App\Http\Requests\Identity\StorePersonDocumentRequest;
use App\Models\Identity\Person;
use App\Models\Identity\PersonDocument;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Identity\PersonDocumentService;
use App\Services\Platform\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PersonDocumentController extends Controller
{
    public function __construct(
        private readonly PersonDocumentService $documentService,
        private readonly SettingsService $settingsService,
    ) {}

    public function index(Request $request, Person $person): Response
    {
        $actor = $this->actor($request);
        Gate::authorize('view', $person);

        return Inertia::render('Identity/People/Documents/Index', [
            'person' => ['id' => $person->getKey(), 'name' => $person->full_name],
            'documents' => $this->documentService->forPerson($person, $actor),
            'documentTypes' => array_map(
                static fn (DocumentType $type): array => ['value' => $type->value, 'label' => $type->label()],
                DocumentType::cases(),
            ),
            'canDeposit' => Gate::allows('create', PersonDocument::class),
            'attachmentAllowedTypes' => $this->settingsService->attachmentAllowedTypes(),
            'attachmentMaxSizeBytes' => $this->settingsService->attachmentMaxSizeBytes(),
        ]);
    }

    public function store(StorePersonDocumentRequest $request, Person $person): RedirectResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('create', PersonDocument::class);
        Gate::authorize('update', $person);
        $attachment = Attachment::query()->where('ulid', $request->attachmentUlid())->firstOrFail();
        $this->documentService->deposit($person, $attachment, $request->documentType(), $actor);

        return redirect()
            ->route('people.documents.index', $person)
            ->with('status', 'Document rangé dans le dossier.');
    }

    public function show(Request $request, Person $person, PersonDocument $document): BinaryFileResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('view', $person);
        $this->ensureBelongsToPerson($document, $person);
        Gate::authorize('view', $document);
        $attachment = $document->attachment()->firstOrFail();
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $document->setRelation('attachment', $attachment);
        $this->documentService->recordConsultation($document, $actor);

        if (config('attachments.x_sendfile.enabled') === true) {
            BinaryFileResponse::trustXSendfileTypeHeader();
        }

        return response()->download(
            $disk->path($attachment->path),
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    public function archive(
        ArchivePersonDocumentRequest $request,
        Person $person,
        PersonDocument $document,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $this->ensureBelongsToPerson($document, $person);
        Gate::authorize('archive', $document);
        $this->documentService->archive($document, $request->archiveReason(), $actor);

        return redirect()
            ->route('people.documents.index', $person)
            ->with('status', 'Document archivé. Il reste consultable dans le dossier.');
    }

    private function ensureBelongsToPerson(PersonDocument $document, Person $person): void
    {
        abort_unless((int) $document->person_id === (int) $person->getKey(), 404);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
