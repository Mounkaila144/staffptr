<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    expenses: { type: Array, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
    can_create: { type: Boolean, required: true },
    can_pay: { type: Boolean, default: false },
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
const expenseColumns = [
    { key: 'reason', label: 'Motif', primary: true },
    { key: 'formatted_amount', label: 'Montant', align: 'right' },
    { key: 'beneficiary', label: 'Bénéficiaire' },
    { key: 'category.name', label: 'Catégorie', hideOnMobile: true },
    { key: 'requester.name', label: 'Demandeur' },
    { key: 'created_at', label: 'Créée le', hideOnMobile: true },
    { key: 'state', label: 'État' },
];
</script>

<template>
    <Head title="Demandes de dépense" />

    <AppLayout title="Demandes de dépense" active-navigation="expenses">
        <div class="grid min-w-0 gap-6">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Demandes de dépense</h1>
                    <p class="max-w-2xl text-ink-secondary">Registre des demandes de dépense : chacune exige deux approbations de la direction avant de pouvoir être payée (une seule, celle de l’autre dirigeant, quand le demandeur est lui-même dirigeant).</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <AppButton v-if="can_pay" variant="secondaire" href="/finances/paiements-depenses">Payer les dépenses approuvées</AppButton>
                    <AppButton v-if="can_create" variant="principal" href="/depenses/create">Nouvelle demande</AppButton>
                </div>
            </header>

            <!-- Filters -->
            <form class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="applyFilters">
                <h2 class="text-section-title">Filtrer le registre</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="filter-state"
                        v-model="filterForm.state"
                        label="État"
                        as="select"
                        :options="[
                            { value: '', label: 'Tous les états' },
                            { value: 'demandee', label: 'Demandée' },
                            { value: 'approuvee', label: 'Approuvée' },
                            { value: 'refusee', label: 'Refusée' },
                            { value: 'payee', label: 'Payée' },
                            { value: 'annulee', label: 'Annulée' },
                        ]"
                    />

                    <FormField
                        id="filter-category"
                        v-model="filterForm.category_id"
                        label="Catégorie"
                        as="select"
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
                :action-href="can_create ? '/depenses/create' : ''"
            />

            <EmptyState
                v-else-if="expenses.length === 0 && isEmptyByFilter"
                title="Aucune demande ne correspond aux filtres."
                reason="Essayez d’autres critères de recherche."
                action-label="Effacer les filtres"
                @action="clearFilters"
            />

            <DataTable v-else :columns="expenseColumns" :rows="expenses" :row-href="(expense) => `/depenses/${expense.id}`" caption="Demandes de dépense">
                <template #cell-reason="{ row }"><span class="grid gap-0.5"><a :href="`/depenses/${row.id}`" class="font-semibold text-ink hover:text-primary">{{ row.reason }}</a><span v-if="row.cancel_reason" class="text-xs font-semibold text-danger">Annulée : {{ row.cancel_reason }}</span></span></template>
                <template #cell-formatted_amount="{ row }"><span class="font-semibold text-ink">{{ row.formatted_amount }}</span></template>
                <template #cell-state="{ row }"><StatusBadge :status="row.state" /></template>
            </DataTable>
        </div>
    </AppLayout>
</template>
