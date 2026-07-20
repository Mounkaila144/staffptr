<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AuditLogIndexRequest;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Platform\AuditLogService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(AuditLogIndexRequest $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        return Inertia::render(
            'Platform/AuditLogs/Index',
            $this->auditLogService->indexData($request->validated()),
        );
    }

    public function export(AuditLogIndexRequest $request): StreamedResponse
    {
        Gate::authorize('viewAny', AuditLog::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $export = $this->auditLogService->prepareExport($actor, $request->validated());

        return response()->streamDownload(
            fn (): int => $this->auditLogService->writeCsv($export['query']),
            $export['filename'],
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
