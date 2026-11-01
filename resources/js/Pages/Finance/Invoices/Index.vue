<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppModal from '../../../Components/AppModal.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    invoices: { type: Array, required: true }, receivables: { type: Array, required: true }, filters: { type: Object, required: true },
    clients: { type: Array, required: true }, contracts: { type: Array, required: true },
});
const formOpen = ref(false);
const cancelInvoice = ref(null);
const receivableColumns = [
    { key: 'number', label: 'Facture', primary: true },
    { key: 'client_name', label: 'Client' },
    { key: 'outstanding_amount_label', label: 'Reste dû', align: 'right' },
    { key: 'age_label', label: 'Ancienneté' },
];
const invoiceColumns = [
    { key: 'number', label: 'Facture', primary: true },
    { key: 'client_name', label: 'Client' },
    { key: 'total_amount_label', label: 'Montant', align: 'right' },
    { key: 'outstanding_amount_label', label: 'Reste dû', align: 'right' },
    { key: 'due_on', label: 'Échéance' },
    { key: 'state', label: 'État' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const amountField = ref(null);
const form = useForm({ client_id: '', contract_id: '', total_amount: 0, issued_on: '', due_on: '' });
const cancelForm = useForm({ reason: '' });
const visibleContracts = computed(() => props.contracts.filter((contract) => Number(contract.client_id) === Number(form.client_id)));

async function openForm() { form.reset(); formOpen.value = true; await nextTick(); amountField.value?.focus(); }
function submit() { form.post('/finances/factures', { preserveScroll: true, onSuccess: () => { formOpen.value = false; form.reset(); } }); }
function frenchDate(iso) { return iso ? String(iso).slice(0, 10).split('-').reverse().join('/') : ''; }
// Sans filtre, le serveur envoie `filters` sous forme de tableau vide : `filters.sort` désigne
// alors la méthode `Array.prototype.sort` et la liste « Trier » restait vide. On ne garde
// que des valeurs texte.
const filterValue = (key) => (typeof props.filters?.[key] === 'string' ? props.filters[key] : '');
const sortChoice = ref(filterValue('sort') || 'age_desc');
const stateChoice = ref(filterValue('state'));
function filter(key, value) { router.get('/finances/factures', { sort: filterValue('sort') || undefined, state: filterValue('state') || undefined, client_id: props.filters?.client_id ?? undefined, [key]: value || undefined }, { preserveState: true, replace: true }); }
function confirmCancellation() {
    cancelForm.patch(`/finances/factures/${cancelInvoice.value.id}/annuler`, { preserveScroll: true, onSuccess: () => { cancelInvoice.value = null; cancelForm.reset(); } });
}
</script>

<template>
    <AppLayout title="Factures et créances" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <PageHeader title="Factures et créances" description="Les statuts sont déduits des encaissements et ne sont jamais saisis.">
                <template #actions><AppButton variant="secondaire" href="/finances/clients">Clients</AppButton><AppButton variant="secondaire" href="/finances/encaissements">Encaissements</AppButton><AppButton variant="principal" @click="openForm">Créer une facture</AppButton></template>
            </PageHeader>

            <AppModal :open="formOpen" size="lg" title="Nouvelle facture" @update:open="(open) => !open && (formOpen = false)">
                <form class="grid min-w-0 gap-4" @submit.prevent="submit">
                <label class="grid gap-2 font-semibold">Client<select v-model="form.client_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option></select></label>
                <label class="grid gap-2 font-semibold">Contrat<select v-model="form.contract_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="contract in visibleContracts" :key="contract.id" :value="contract.id">{{ contract.reference }} — {{ contract.title }}</option></select></label>
                <FormField ref="amountField" id="invoice-amount" v-model="form.total_amount" variant="money" label="Montant en XOF" required :error="form.errors.total_amount" />
                <div class="grid gap-3 sm:grid-cols-2"><FormField id="invoice-issued" v-model="form.issued_on" variant="date" label="Date d’émission" required :error="form.errors.issued_on" /><FormField id="invoice-due" v-model="form.due_on" variant="date" label="Date d’échéance" required :error="form.errors.due_on" /></div>
                <p class="rounded-lg bg-neutral-soft p-3 text-sm">Le numéro unique est attribué automatiquement. Aucun PDF ni aucune relance ne sont générés.</p>
                <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal" :busy="form.processing">Enregistrer</AppButton><AppButton type="button" variant="secondaire" @click="formOpen = false">Annuler</AppButton></div>
            </form>
            </AppModal>

            <section class="grid min-w-0 gap-4" aria-labelledby="receivables-title">
                <div class="flex min-w-0 flex-wrap items-end justify-between gap-3"><div><h2 id="receivables-title" class="text-section-title">Créances échues</h2><p class="text-sm text-ink-secondary">Montant restant et ancienneté calculés à la date du jour.</p></div><label class="grid gap-1 text-sm font-semibold">Trier<select v-model="sortChoice" class="touch-target rounded-lg border border-separator px-3" @change="filter('sort', sortChoice)"><option value="age_desc">Plus anciennes</option><option value="outstanding_desc">Montant restant</option></select></label></div>
                <EmptyState v-if="receivables.length === 0" title="Aucune créance échue." reason="Toutes les factures arrivées à échéance sont soldées ou annulées." />
                <DataTable v-else :columns="receivableColumns" :rows="receivables" caption="Créances échues">
                    <template #cell-outstanding_amount_label="{ row }"><span class="font-semibold text-warning">{{ row.outstanding_amount_label }}</span></template>
                </DataTable>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="invoices-title">
                <div class="flex flex-wrap items-end justify-between gap-3"><h2 id="invoices-title" class="text-section-title">Toutes les factures</h2><label class="grid gap-1 text-sm font-semibold">État<select v-model="stateChoice" class="touch-target rounded-lg border border-separator px-3" @change="filter('state', stateChoice)"><option value="">Tous</option><option value="impayee">Impayée</option><option value="partiellement_payee">Partiellement payée</option><option value="payee">Payée</option><option value="annulee">Annulée</option></select></label></div>
                <EmptyState v-if="invoices.length === 0" title="Aucune facture." reason="Créez la première facture depuis un contrat actif." />
                <DataTable v-else :columns="invoiceColumns" :rows="invoices" caption="Toutes les factures">
                    <template #cell-client_name="{ row }"><span class="grid gap-0.5"><span>{{ row.client_name }}</span><span class="text-xs text-ink-muted">{{ row.contract_reference }}</span></span></template>
                    <template #cell-total_amount_label="{ row }"><span class="font-semibold text-ink">{{ row.total_amount_label }}</span></template>
                    <template #cell-outstanding_amount_label="{ row }"><span v-if="row.state === 'annulee'" class="text-ink-muted">Rien à encaisser</span><span v-else>{{ row.outstanding_amount_label }}</span></template>
                    <template #cell-due_on="{ row }">{{ frenchDate(row.due_on) }}</template>
                    <template #cell-state="{ row }"><span class="grid justify-items-start gap-1"><StatusBadge :status="row.state" /><span v-if="row.cancellation_reason" class="max-w-xs text-xs text-ink-secondary">Motif : {{ row.cancellation_reason }}</span></span></template>
                    <template #cell-actions="{ row }"><AppButton v-if="row.state === 'impayee'" variant="discret" @click="cancelInvoice = row">Annuler avec motif</AppButton></template>
                </DataTable>
            </section>

            <AppModal :open="Boolean(cancelInvoice)" :title="`Annuler ${cancelInvoice?.number ?? ''} sans la supprimer`" description="La facture reste consultable avec son motif d’annulation." @update:open="(open) => !open && (cancelInvoice = null)">
                <form id="invoice-cancel-form" class="grid gap-3" @submit.prevent="confirmCancellation"><label class="grid gap-2 font-semibold">Motif<textarea v-model="cancelForm.reason" class="min-h-24 rounded-lg border border-separator p-3" required minlength="10" /></label><p v-if="cancelForm.errors.reason" class="text-sm font-semibold text-danger">⚠ {{ cancelForm.errors.reason }}</p></form>
                <template #footer><AppButton type="button" variant="secondaire" @click="cancelInvoice = null">Retour</AppButton><AppButton type="submit" form="invoice-cancel-form" variant="danger" :busy="cancelForm.processing">Confirmer l’annulation</AppButton></template>
            </AppModal>
        </div>
    </AppLayout>
</template>
