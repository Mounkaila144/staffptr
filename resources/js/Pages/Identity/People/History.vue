<script setup>
import { Link } from '@inertiajs/vue3';
import EmptyState from '../../../Components/EmptyState.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    person: { type: Object, required: true },
    history: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="`Historique de ${person.name}`" :back-href="`/personnes/${person.id}`" back-label="Retour à la fiche" active-navigation="team">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Historique de la fiche</h1>
                <p class="break-words text-ink-secondary">{{ person.name }}</p>
            </header>

            <EmptyState
                v-if="history.data.length === 0"
                title="Aucun changement enregistré pour cette fiche."
                reason="Les futurs changements de rôle, service, responsable ou statut apparaîtront ici."
            />

            <ol v-else class="grid min-w-0 gap-4" aria-label="Modifications historiques">
                <li v-for="entry in history.data" :key="entry.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                    <p class="break-words font-semibold">{{ entry.field_label }}</p>
                    <p class="grid min-w-0 gap-1 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] sm:items-center">
                        <span class="break-words rounded-lg bg-neutral-soft px-3 py-2">{{ entry.old_value }}</span>
                        <span class="text-center font-semibold text-ink-secondary" aria-hidden="true">→</span>
                        <span class="break-words rounded-lg bg-action-soft px-3 py-2">{{ entry.new_value }}</span>
                    </p>
                    <p class="break-words text-sm text-ink-secondary">Par {{ entry.author }} · {{ entry.changed_at }}</p>
                    <p v-if="entry.reason" class="break-words text-sm"><span class="font-semibold">Motif :</span> {{ entry.reason }}</p>
                </li>
            </ol>

            <nav v-if="history.last_page > 1" class="flex flex-wrap gap-2" aria-label="Pagination de l’historique">
                <Link
                    v-for="link in history.links"
                    :key="`${link.label}-${link.url}`"
                    :href="link.url ?? '#'"
                    :aria-disabled="link.url === null"
                    :class="[
                        'touch-target inline-flex min-w-11 items-center justify-center rounded-lg border px-3 font-semibold',
                        link.active ? 'border-action bg-action text-white' : 'border-separator bg-surface',
                        link.url === null ? 'pointer-events-none opacity-50' : 'hover:bg-action-soft',
                    ]"
                    preserve-scroll
                >
                    <span v-html="link.label" />
                </Link>
            </nav>
        </div>
    </AppLayout>
</template>
