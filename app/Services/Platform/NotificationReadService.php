<?php

namespace App\Services\Platform;

use App\Models\Identity\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationReadService
{
    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();
        $notification->markAsRead();

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function markForLink(User $user, string $link): int
    {
        return $user->unreadNotifications()
            ->where('data->link', $link)
            ->update(['read_at' => now()]);
    }
}
