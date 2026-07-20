<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, required: true },
    filtersActive: { type: Boolean, required: true },
    journalHasEntries: { type: Boolean, required: true },
    authors: { type: Array, required: true },
    objectTypes: { type: Array, required: true },
    actions: { type: Array, required: true },
});

const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');
const exportFields = computed(() => Object.entries(props.filters)
    .filter(([, value]) => value !== null && value !== ''));

function resetFilters() {
    router.get('/journal-audit');
}
</script>

<template>
    <Head title="Journal d’audit" />
    <AppLayout title="Journal d’audit">
        <div class="grid gap-8">
            <header class="grid gap-2">
                <h1 class="text-screen-title">Journal d’audit</h1>
                <p class="text-ink-secondary">Consultez les opérations enregistrées et leur différentiel, en heure de Niamey.</p>
            </header>

            <form method="get" action="/journal-audit" class="grid gap-4 rounded-xl border border-separator bg-surface p-4" aria-label="Filtrer le journal d’audit">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="grid gap-2 text-field-label font-semibold">Auteur
                        <select name="actor_id" :value="filters.actor_id ?? ''" class="touch-target rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="">Tous les auteurs</option>
                            <option v-for="author in authors" :key="author.value" :value="author.value">{{ author.label }}</option>
                        </select>
                    </label>
                    <FormField id="from" v-model="from" label="Du" variant="date" />
                    <FormField id="to" v-model="to" label="Au" variant="date" />
                    <label class="grid gap-2 text-field-label font-semibold">Type d’objet
                        <select name="auditable_type" :value="filters.auditable_type ?? ''" class="touch-target rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="">Tous les objets</option>
                            <option v-for="type in objectTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </label>
                    <label class="grid gap-2 text-field-label font-semibold">Action
                        <select name="action" :value="filters.action ?? ''" class="touch-target rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="">Toutes les actions</option>
                            <option v-for="action in actions" :key="action.value" :value="action.value">{{ action.label }}</option>
                        </select>
                    </label>
                </div>
                <div class="flex flex-wrap gap-3">
                    <AppButton type="submit" variant="principal">Filtrer</AppButton>
                    <AppButton v-if="filtersActive" variant="discret" @click="resetFilters">Réinitialiser les filtres</AppButton>
                </div>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-ink-secondary">{{ entries.total }} entrée{{ entries.total > 1 ? 's' : '' }} pour cette consultation.</p>
                <form method="get" action="/journal-audit/export">
                    <input v-for="([name, value]) in exportFields" :key="name" type="hidden" :name="name" :value="value">
                    <AppButton type="submit" variant="secondaire">Exporter en CSV</AppButton>
                </form>
            </div>

            <section aria-labelledby="entries-title" class="grid gap-4">
                <h2 id="entries-title" class="sr-only">Entrées du journal</h2>

                <div v-if="entries.data.length" class="grid gap-4">
                    <article v-for="entry in entries.data" :key="entry.id" class="grid gap-4 rounded-xl border border-separator bg-surface p-4">
                        <header class="flex flex-wrap items-start justify-between gap-3">
                            <div class="grid gap-1">
                                <p class="font-semibold">{{ entry.actor }}</p>
                                <p class="text-sm text-ink-secondary">{{ entry.occurred_at }} · {{ entry.object }}</p>
                            </div>
                            <StatusBadge status="audit" variant="avec contexte" :context="entry.action" />
                        </header>

                        <dl v-if="entry.changes.length" class="grid gap-3">
                            <div v-for="change in entry.changes" :key="change.field" class="grid gap-2 rounded-lg bg-neutral-soft p-3 sm:grid-cols-2">
                                <dt class="font-semibold sm:col-span-2">{{ change.label }}</dt>
                                <dd><span class="block text-xs font-semibold uppercase text-ink-secondary">Ancienne valeur</span><span class="break-words">{{ change.old }}</span></dd>
                                <dd><span class="block text-xs font-semibold uppercase text-ink-secondary">Nouvelle valeur</span><span class="break-words">{{ change.new }}</span></dd>
                            </div>
                        </dl>
                        <p v-else class="text-sm text-ink-secondary">Aucun différentiel de valeur pour cette opération.</p>

                        <p v-if="entry.reason" class="text-sm"><span class="font-semibold">Motif :</span> {{ entry.reason }}</p>
                        <details class="text-sm text-ink-secondary">
                            <summary class="touch-target flex cursor-pointer items-center font-semibold">Références techniques</summary>
                            <p class="break-all">Objet : {{ entry.object_technical }} · Action : {{ entry.action_technical }}</p>
                        </details>
                    </article>
                </div>

                <EmptyState
                    v-else-if="filtersActive"
                    tone="filter"
                    title="Aucune entrée pour ces filtres."
                    reason="Modifiez les critères ou réinitialisez les filtres pour retrouver le journal complet."
                    @action="resetFilters"
                />
                <EmptyState
                    v-else-if="!journalHasEntries"
                    title="Le journal ne contient encore aucune entrée."
                    reason="Les opérations sensibles apparaîtront ici dès leur premier enregistrement."
                />
                <EmptyState
                    v-else
                    title="Aucune entrée sur cette page."
                    reason="Revenez à la page précédente pour poursuivre la consultation."
                />

                <nav v-if="entries.prev_page_url || entries.next_page_url" class="flex items-center justify-between gap-3" aria-label="Pagination du journal d’audit">
                    <Link v-if="entries.prev_page_url" :href="entries.prev_page_url" class="touch-target inline-flex items-center rounded-lg px-3 font-semibold text-primary">Précédent</Link><span v-else />
                    <p class="text-sm text-ink-secondary">Page {{ entries.current_page }} sur {{ entries.last_page }}</p>
                    <Link v-if="entries.next_page_url" :href="entries.next_page_url" class="touch-target inline-flex items-center rounded-lg px-3 font-semibold text-primary">Suivant</Link><span v-else />
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
