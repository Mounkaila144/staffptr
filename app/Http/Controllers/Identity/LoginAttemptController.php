<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\LoginAttemptIndexRequest;
use App\Models\Identity\LoginAttempt;
use App\Services\Identity\LoginHistoryService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoginAttemptController extends Controller
{
    public function __construct(private readonly LoginHistoryService $loginHistoryService) {}

    public function index(LoginAttemptIndexRequest $request): Response
    {
        Gate::authorize('viewAny', LoginAttempt::class);

        return Inertia::render(
            'Identity/LoginAttempts/Index',
            $this->loginHistoryService->indexData($request->validated()),
        );
    }
}
