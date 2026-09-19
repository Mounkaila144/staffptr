<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    expenses: { type: Array, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
    can_create: { type: Boolean, required: true },
});

const filterForm = useForm({
    state: props.filters.state || '',
    category_id: props.filters.category_id || '',
    requester_id: props.filters.requester_id || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function applyFilters() {
    filterForm.get('/depenses', { preserveScroll: true });
}

function clearFilters() {
    filterForm.state = '';
    filterForm.category_id = '';
    filterForm.requester_id = '';
    filterForm.start_date = '';
    filterForm.end_date = '';
    filterForm.get('/depenses', { preserveScroll: true });
}

const isEmptyByFilter = computed(() =>
    filterForm.state !== '' ||
    filterForm.category_id !== '' ||
    filterForm.requester_id !== '' ||
    (filterForm.start_date !== '' && filterForm.end_date !== '')
);

const isEmptyByAbsence = computed(() => !isEmptyByFilter.value);
</script>

<template>
    <Head title="Demandes de dépense" />

    <AppLayout title="Demandes de dépense" back-label="Paramètres" back-href="/parametres" active-navigation="settings">
        <div class="grid min-w-0 gap-6">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Demandes de dépense</h1>
                    <p class="max-w-2xl text-ink-secondary">Registre des demandes de dépense en attente de double approbation.</p>
                </div>
                <AppButton v-if="can_create" variant="principal" href="/depenses/create">Nouvelle demande</AppButton>
            </header>

            <!-- Filters -->
            <form class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="applyFilters">
                <h2 class="text-section-title">Filtrer le registre</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="filter-state"
                        v-model="filterForm.state"
                        label="État"
                        type="select"
                        :options="[
                            { value: '', label: 'Tous les états' },
                            { value: 'demandee', label: 'Demandée' },
                            { value: 'approuvee', label: 'Approuvée' },
                            { value: 'refusee', label: 'Refusée' },
                            { value: 'annulee', label: 'Annulée' },
                        ]"
                    />

                    <FormField
                        id="filter-category"
                        v-model="filterForm.category_id"
                        label="Catégorie"
                        type="select"
                        :options="[
                            { value: '', label: 'Toutes les catégories' },
                            ...categories.map(c => ({ value: c.id, label: c.name })),
                        ]"
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <AppButton type="submit" variant="principal" :busy="filterForm.processing">Filtrer</AppButton>
                    <AppButton variant="secondaire" @click="clearFilters">Effacer</AppButton>
                </div>
            </form>

            <EmptyState
                v-if="expenses.length === 0 && isEmptyByAbsence"
                title="Aucune demande de dépense pour ce mois."
                reason="Le registre est vide. Créez une demande pour commencer le suivi."
                action-label="Nouvelle demande"
                :action-href="can_create ? '/depenses/create' : undefined"
            />

            <EmptyState
                v-else-if="expenses.length === 0 && isEmptyByFilter"
                title="Aucune demande ne correspond aux filtres."
                reason="Essayez d’autres critères de recherche."
                action-label="Effacer les filtres"
                @action="clearFilters"
            />

            <ul v-else class="grid min-w-0 gap-3">
                <li v-for="expense in expenses" :key="expense.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                        <h2 class="min-w-0 break-words text-card-title">{{ expense.reason }}</h2>
                        <StatusBadge :status="expense.state" />
                    </div>

                    <div class="grid gap-2 text-sm">
                        <p class="font-semibold text-primary">{{ expense.formatted_amount }}</p>
                        <p class="text-ink-secondary">Bénéficiaire : {{ expense.beneficiary }}</p>
                        <p class="text-ink-secondary">Catégorie : {{ expense.category.name }}</p>
                        <p class="text-ink-secondary">Demandeur : {{ expense.requester.name }}</p>
                        <p class="text-ink-secondary">Créée le : {{ expense.created_at }}</p>
                        <p v-if="expense.cancel_reason" class="text-sm font-semibold text-danger">Annulée : {{ expense.cancel_reason }}</p>
                    </div>

                    <AppButton variant="secondaire" :href="`/depenses/${expense.id}`">Voir les détails</AppButton>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
