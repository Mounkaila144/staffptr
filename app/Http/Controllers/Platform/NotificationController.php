<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Platform\NotificationReadService;
use App\Support\DateTimeFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationReadService $notificationReadService) {}

    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->getKey(),
                'message' => (string) data_get($notification->data, 'message'),
                'link' => $this->safeInternalLink((string) data_get($notification->data, 'link')),
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at !== null
                    ? DateTimeFormatter::format($notification->created_at)
                    : '',
            ]);

        return Inertia::render('Platform/Notifications/Index', [
            'notificationItems' => $notifications,
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->notificationReadService->markAsRead(
            $this->authenticatedUser($request),
            $notification,
        );

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notificationReadService->markAllAsRead($this->authenticatedUser($request));

        return back();
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function safeInternalLink(string $link): string
    {
        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            return route('home', absolute: false);
        }

        return $link;
    }
}
