<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Notifications\GenericLinkedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

class NotificationCenterHttpTest extends IdentityTestCase
{
    public function test_ac_1_center_and_unread_counter_are_available_from_authenticated_pages(): void
    {
        $user = User::factory()->active()->create();
        $this->createNotification($user, 'Première action.');
        $this->createNotification($user, 'Action déjà vue.', '/', read: true);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Platform/Notifications/Index')
                ->has('notificationItems.data', 2)
                ->where('notifications.unread_count', 1));

        $layout = (string) file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $this->assertStringContainsString('href="/notifications"', $layout);
        $this->assertStringContainsString('unreadNotificationCount', $layout);
    }

    public function test_ac_3_and_4_direct_link_reaches_authorized_object_in_two_interactions(): void
    {
        $user = User::factory()->active()->create();
        $notification = $this->createNotification($user, 'Ouvrez cet élément.', '/');

        $center = $this->actingAs($user)->get(route('notifications.index'));
        $center->assertInertia(fn (Assert $page): Assert => $page
            ->where('notificationItems.data.0.link', '/')
            ->where('notificationItems.data.0.id', $notification->getKey()));

        $this->actingAs($user)->get('/')->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_ac_5_explicit_and_implicit_reading_are_both_scoped_to_the_owner(): void
    {
        $owner = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $explicit = $this->createNotification($owner, 'Lecture explicite.', '/personnes');
        $implicit = $this->createNotification($owner, 'Lecture implicite.', '/');
        $otherNotification = $this->createNotification($other, 'Notification privée.', '/');

        $this->actingAs($owner)
            ->patch(route('notifications.read', $explicit->getKey()))
            ->assertRedirect();
        $this->assertNotNull($explicit->fresh()->read_at);
        $this->assertNull($implicit->fresh()->read_at);

        $this->actingAs($owner)->get(route('home'))->assertOk();
        $this->assertNotNull($implicit->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_ac_5_account_cannot_view_or_mark_another_accounts_notification(): void
    {
        $owner = User::factory()->active()->create();
        $intruder = User::factory()->active()->create();
        $private = $this->createNotification($owner, 'Réservée au propriétaire.');
        $this->createNotification($intruder, 'Visible par le compte connecté.');

        $this->actingAs($intruder)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('notificationItems.data', 1)
                ->where('notificationItems.data.0.message', 'Visible par le compte connecté.'));

        $this->actingAs($intruder)
            ->patch(route('notifications.read', $private->getKey()))
            ->assertNotFound();
        $this->assertNull($private->fresh()->read_at);
    }

    public function test_ac_5_mark_all_only_updates_the_authenticated_accounts_notifications(): void
    {
        $owner = User::factory()->active()->create();
        $other = User::factory()->active()->create();
        $first = $this->createNotification($owner, 'Première.');
        $second = $this->createNotification($owner, 'Deuxième.');
        $private = $this->createNotification($other, 'Autre compte.');

        $this->actingAs($owner)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($private->fresh()->read_at);
    }

    public function test_ac_7_empty_center_uses_the_positive_up_to_date_message(): void
    {
        $user = User::factory()->active()->create();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page): Assert => $page->has('notificationItems.data', 0));

        $page = (string) file_get_contents(resource_path('js/Pages/Platform/Notifications/Index.vue'));
        $this->assertStringContainsString('title="Vous êtes à jour."', $page);
        $this->assertStringContainsString('tone="positive"', $page);
    }

    public function test_ac_8_counter_is_shared_by_inertia_without_a_polling_endpoint(): void
    {
        $user = User::factory()->active()->create();
        $this->createNotification($user, 'À lire.');
        $this->createNotification($user, 'Déjà lue.', '/', read: true);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page): Assert => $page->where('notifications.unread_count', 1));

        $routeNames = collect(app('router')->getRoutes()->getRoutesByName())->keys();
        $this->assertFalse($routeNames->contains('notifications.unread-count'));
        $middleware = (string) file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
        $this->assertStringContainsString("'unread_count'", $middleware);
        $this->assertStringContainsString('unreadNotifications()->count()', $middleware);
    }

    private function createNotification(
        User $user,
        string $message,
        string $link = '/',
        bool $read = false,
    ): DatabaseNotification {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => GenericLinkedNotification::class,
            'data' => [
                'message' => $message,
                'link' => $link,
            ],
            'read_at' => $read ? now() : null,
        ]);

        return $notification;
    }
}
