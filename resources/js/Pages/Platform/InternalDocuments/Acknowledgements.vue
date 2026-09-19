<script setup>
import EmptyState from '../../../Components/EmptyState.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    document: { type: Object, required: true },
    users: { type: Array, required: true },
});
</script>

<template>
    <AppLayout title="Acceptations" :back-label="document.title" :back-href="`/documents-internes/${document.id}`" active-navigation="documents">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">État des acceptations</h1>
                <p class="break-words font-semibold">{{ document.title }} · version {{ document.version_number }}</p>
                <p class="text-ink-secondary">Demandée depuis {{ document.age_days }} jour{{ document.age_days > 1 ? 's' : '' }} · publication le {{ document.published_at }}</p>
            </header>

            <EmptyState v-if="users.length === 0" title="Aucun compte concerné." reason="Aucun compte actif autorisé ne doit accepter cette version." />

            <ul v-else class="grid min-w-0 gap-3 sm:grid-cols-2" aria-label="État des acceptations par utilisateur">
                <li v-for="user in users" :key="user.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                        <h2 class="min-w-0 break-words font-semibold">{{ user.name }}</h2>
                        <StatusBadge :status="user.accepted ? 'validé' : 'en attente'" />
                    </div>
                    <p v-if="user.accepted" class="text-sm">Accepté le {{ user.accepted_at }}.</p>
                    <p v-else class="text-sm">Acceptation attendue depuis {{ user.age_days }} jour{{ user.age_days > 1 ? 's' : '' }}.</p>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
