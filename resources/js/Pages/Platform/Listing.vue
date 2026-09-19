<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import EmptyState from '../../Components/EmptyState.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    listing: { type: Object, required: true },
    filterFields: { type: Array, required: true },
    appliedFilters: { type: Object, required: true },
    savedFilters: { type: Array, default: () => [] },
    canSaveFilter: { type: Boolean, default: false },
});

// AC 12 : sur téléphone, les filtres vivent dans un panneau escamotable. Une fois appliqué, il se
// referme — il ne doit jamais masquer les résultats qu'il vient de produire.
const panelOpen = ref(false);
const draft = ref({ ...props.appliedFilters });
const saveForm = useForm({ list_key: props.listing.key, name: '', criteria: {} });

function navigate(params) {
    router.get(`/listes/${props.listing.key}`, params, { preserveScroll: true, preserveState: true });
}

function apply() {
    panelOpen.value = false;
    navigate({ ...draft.value, sort: props.listing.sort, direction: props.listing.direction });
}

// AC 11 : un filtre actif se retire seul, sans toucher aux autres.
function removeFilter(key) {
    const next = { ...props.appliedFilters };
    delete next[key];
    draft.value = next;
    navigate({ ...next, sort: props.listing.sort, direction: props.listing.direction });
}

function resetFilters() {
    draft.value = {};
    navigate({ sort: props.listing.sort, direction: props.listing.direction });
}

function sortBy(column) {
    const direction = props.listing.sort === column && props.listing.direction === 'desc' ? 'asc' : 'desc';
    navigate({ ...props.appliedFilters, sort: column, direction });
}

function applySaved(filter) {
    draft.value = { ...filter.criteria };
    navigate({ ...filter.criteria, sort: props.listing.sort, direction: props.listing.direction });
}

function saveCurrent() {
    saveForm.criteria = { ...props.appliedFilters };
    saveForm.post('/filtres-enregistres', { preserveScroll: true, onSuccess: () => saveForm.reset('name') });
}

function exportUrl() {
    const params = new URLSearchParams({
        ...props.appliedFilters,
        sort: props.listing.sort,
        direction: props.listing.direction,
    });

    return `${props.listing.export_url}?${params.toString()}`;
}
</script>

<template>
    <Head :title="listing.label" />
    <AppLayout :title="listing.label" active-navigation="home">
        <div class="grid min-w-0 gap-6">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-1">
                    <h1 class="break-words text-page-title">{{ listing.label }}</h1>
                    <p class="text-ink-secondary">{{ listing.total }} ligne{{ listing.total > 1 ? 's' : '' }} dans votre périmètre.</p>
                </div>
                <!--
                    AC 13 : l'export reprend exactement les filtres et le tri affichés. C'est la
                    même URL, à `/export` près.
                -->
                <a
                    :href="exportUrl()"
                    class="touch-target inline-flex items-center rounded-lg border-2 border-primary px-4 font-semibold text-primary"
                    data-testid="export-link"
                >
                    Exporter en CSV
                </a>
            </header>

            <button
                type="button"
                class="touch-target inline-flex w-fit items-center rounded-lg border border-primary px-4 font-semibold text-primary sm:hidden"
                :aria-expanded="panelOpen"
                aria-controls="filter-panel"
                @click="panelOpen = !panelOpen"
            >
                {{ panelOpen ? 'Masquer les filtres' : 'Filtrer' }}
            </button>

            <section
                id="filter-panel"
                class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4"
                :class="panelOpen ? '' : 'hidden sm:grid'"
                aria-labelledby="filter-panel-title"
            >
                <h2 id="filter-panel-title" class="text-section-title">Filtres</h2>

                <div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="field in filterFields" :key="field" class="grid min-w-0 gap-1">
                        <label :for="`filter-${field}`" class="text-field-label font-semibold">{{ field }}</label>
                        <input
                            :id="`filter-${field}`"
                            v-model="draft[field]"
                            :type="field === 'from' || field === 'to' ? 'date' : 'text'"
                            class="min-h-12 w-full min-w-0 rounded-lg border border-separator bg-surface px-3"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <AppButton type="button" variant="principal" @click="apply">Appliquer</AppButton>
                    <AppButton v-if="listing.filters_active" type="button" variant="secondaire" @click="resetFilters">
                        Réinitialiser les filtres
                    </AppButton>
                </div>
            </section>

            <!-- AC 11 : les filtres actifs restent visibles en permanence et se retirent un par un. -->
            <ul v-if="listing.active_filters.length" class="flex min-w-0 flex-wrap gap-2" aria-label="Filtres actifs">
                <li v-for="filter in listing.active_filters" :key="filter.key">
                    <button
                        type="button"
                        class="touch-target inline-flex items-center gap-2 rounded-full border border-primary px-3 font-semibold text-primary"
                        @click="removeFilter(filter.key)"
                    >
                        {{ filter.label }}
                        <span aria-hidden="true">×</span>
                        <span class="sr-only">Retirer ce filtre</span>
                    </button>
                </li>
            </ul>

            <section v-if="canSaveFilter" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="saved-filters-title">
                <h2 id="saved-filters-title" class="text-section-title">Mes filtres enregistrés</h2>
                <p class="text-sm text-ink-secondary">Ces filtres ne sont visibles que par vous.</p>

                <ul v-if="savedFilters.length" class="flex flex-wrap gap-2">
                    <li v-for="filter in savedFilters" :key="filter.id">
                        <button type="button" class="touch-target inline-flex items-center rounded-lg border border-primary px-3 font-semibold text-primary" @click="applySaved(filter)">
                            {{ filter.name }}
                        </button>
                    </li>
                </ul>

                <form class="flex min-w-0 flex-wrap items-end gap-3" @submit.prevent="saveCurrent">
                    <div class="grid min-w-0 flex-1 gap-1">
                        <label for="saved-filter-name" class="text-field-label font-semibold">Nom du filtre</label>
                        <input
                            id="saved-filter-name"
                            v-model="saveForm.name"
                            type="text"
                            class="min-h-12 w-full min-w-0 rounded-lg border border-separator bg-surface px-3"
                            :aria-invalid="saveForm.errors.name ? 'true' : 'false'"
                        />
                        <p v-if="saveForm.errors.name" class="text-sm font-semibold text-danger">⚠ {{ saveForm.errors.name }}</p>
                    </div>
                    <AppButton type="submit" variant="secondaire" :busy="saveForm.processing">Enregistrer les filtres actuels</AppButton>
                </form>
            </section>

            <!-- AC 10 : trois vides différents, trois messages différents. -->
            <EmptyState
                v-if="listing.items.length === 0"
                :tone="listing.filters_active ? 'filter' : 'neutral'"
                title="Aucune ligne à afficher"
                :reason="listing.empty_message"
                @action="resetFilters"
            />

            <!-- La table défile dans son propre conteneur : la page, elle, ne défile jamais latéralement. -->
            <div v-else class="min-w-0 overflow-x-auto rounded-xl border border-separator bg-surface">
                <table class="w-full min-w-max border-collapse text-left">
                    <thead>
                        <tr class="border-b border-separator">
                            <th v-for="(header, index) in listing.headers" :key="header" scope="col" class="p-3 font-semibold">
                                <button
                                    v-if="listing.sortable.includes(listing.sortable[index])"
                                    type="button"
                                    class="touch-target inline-flex items-center gap-1 font-semibold"
                                    @click="sortBy(listing.sortable[index] ?? listing.sort)"
                                >
                                    {{ header }}
                                </button>
                                <span v-else>{{ header }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in listing.items" :key="item.id" class="border-b border-separator last:border-b-0">
                            <td v-for="(cell, index) in item.cells" :key="`${item.id}-${index}`" class="p-3">{{ cell }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="listing.last_page > 1" class="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination">
                <button
                    type="button"
                    class="touch-target inline-flex items-center rounded-lg border border-primary px-4 font-semibold text-primary disabled:opacity-50"
                    :disabled="listing.page <= 1"
                    @click="navigate({ ...appliedFilters, sort: listing.sort, direction: listing.direction, page: listing.page - 1 })"
                >
                    Page précédente
                </button>
                <span>Page {{ listing.page }} sur {{ listing.last_page }}</span>
                <button
                    type="button"
                    class="touch-target inline-flex items-center rounded-lg border border-primary px-4 font-semibold text-primary disabled:opacity-50"
                    :disabled="listing.page >= listing.last_page"
                    @click="navigate({ ...appliedFilters, sort: listing.sort, direction: listing.direction, page: listing.page + 1 })"
                >
                    Page suivante
                </button>
            </nav>
        </div>
    </AppLayout>
</template>
