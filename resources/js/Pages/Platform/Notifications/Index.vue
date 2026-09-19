<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    notificationItems: { type: Object, required: true },
});

const processingId = ref('');

function markAsRead(notificationId) {
    processingId.value = notificationId;
    router.patch(`/notifications/${notificationId}/lue`, {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = ''; },
    });
}

function markAllAsRead() {
    processingId.value = 'all';
    router.patch('/notifications/tout-lu', {}, {
        preserveScroll: true,
        onFinish: () => { processingId.value = ''; },
    });
}
</script>

<template>
    <AppLayout title="Notifications" back-label="Accueil" active-navigation="notifications">
        <div class="grid min-w-0 gap-6">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Ce qui vous attend</h1>
                    <p class="text-ink-secondary">Retrouvez ici les éléments qui demandent votre attention.</p>
                </div>
                <AppButton
                    v-if="notificationItems.data.some((notification) => !notification.is_read)"
                    variant="secondaire"
                    :busy="processingId === 'all'"
                    busy-label="Marquage en cours"
                    @click="markAllAsRead"
                >
                    Tout marquer comme lu
                </AppButton>
            </header>

            <EmptyState
                v-if="notificationItems.data.length === 0"
                tone="positive"
                title="Vous êtes à jour."
                reason="Aucune action ne vous attend pour le moment."
            />

            <ul v-else class="grid min-w-0 gap-3" aria-label="Liste des notifications">
                <li
                    v-for="notification in notificationItems.data"
                    :key="notification.id"
                    class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="grid min-w-0 gap-2">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="font-semibold" :class="notification.is_read ? 'text-ink-secondary' : 'text-ink'">
                                {{ notification.is_read ? 'Lue' : 'Non lue' }}
                            </span>
                            <span class="text-sm text-ink-secondary">{{ notification.created_at }}</span>
                        </div>
                        <p class="break-words">{{ notification.message }}</p>
                        <a :href="notification.link" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">
                            Ouvrir l’élément concerné
                        </a>
                    </div>
                    <AppButton
                        v-if="!notification.is_read"
                        variant="discret"
                        :busy="processingId === notification.id"
                        busy-label="Marquage en cours"
                        @click="markAsRead(notification.id)"
                    >
                        Marquer comme lue
                    </AppButton>
                </li>
            </ul>

            <nav v-if="notificationItems.last_page > 1" class="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination des notifications">
                <a v-if="notificationItems.prev_page_url" :href="notificationItems.prev_page_url" class="touch-target inline-flex items-center font-semibold text-primary">Page précédente</a>
                <span class="text-sm text-ink-secondary">Page {{ notificationItems.current_page }} sur {{ notificationItems.last_page }}</span>
                <a v-if="notificationItems.next_page_url" :href="notificationItems.next_page_url" class="touch-target inline-flex items-center font-semibold text-primary">Page suivante</a>
            </nav>
        </div>
    </AppLayout>
</template>
