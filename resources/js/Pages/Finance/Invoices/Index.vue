<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    invoices: { type: Array, required: true }, receivables: { type: Array, required: true }, filters: { type: Object, required: true },
    clients: { type: Array, required: true }, contracts: { type: Array, required: true },
});
const formOpen = ref(false);
const cancelInvoice = ref(null);
const amountField = ref(null);
const form = useForm({ client_id: '', contract_id: '', total_amount: 0, issued_on: '', due_on: '' });
const cancelForm = useForm({ reason: '' });
const visibleContracts = computed(() => props.contracts.filter((contract) => Number(contract.client_id) === Number(form.client_id)));

async function openForm() { form.reset(); formOpen.value = true; await nextTick(); amountField.value?.focus(); }
function submit() { form.post('/finances/factures', { preserveScroll: true, onSuccess: () => { formOpen.value = false; form.reset(); } }); }
function filter(key, value) { router.get('/finances/factures', { ...props.filters, [key]: value || undefined }, { preserveState: true, replace: true }); }
function confirmCancellation() {
    cancelForm.patch(`/finances/factures/${cancelInvoice.value.id}/annuler`, { preserveScroll: true, onSuccess: () => { cancelInvoice.value = null; cancelForm.reset(); } });
}
</script>

<template>
    <AppLayout title="Factures et créances" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap justify-between gap-3"><div><h1 class="break-words text-page-title">Factures et créances</h1><p class="text-ink-secondary">Les statuts sont déduits des encaissements et ne sont jamais saisis.</p></div><AppButton variant="principal" @click="openForm">Créer une facture</AppButton></header>

            <form v-if="formOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">Nouvelle facture</h2>
                <label class="grid gap-2 font-semibold">Client<select v-model="form.client_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option></select></label>
                <label class="grid gap-2 font-semibold">Contrat<select v-model="form.contract_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="contract in visibleContracts" :key="contract.id" :value="contract.id">{{ contract.reference }} — {{ contract.title }}</option></select></label>
                <FormField ref="amountField" id="invoice-amount" v-model="form.total_amount" type="number" min="1" label="Montant en XOF" required :error="form.errors.total_amount" />
                <div class="grid gap-3 sm:grid-cols-2"><FormField id="invoice-issued" v-model="form.issued_on" type="date" label="Date d’émission" required :error="form.errors.issued_on" /><FormField id="invoice-due" v-model="form.due_on" type="date" label="Date d’échéance" required :error="form.errors.due_on" /></div>
                <p class="rounded-lg bg-neutral-soft p-3 text-sm">Le numéro unique est attribué automatiquement. Aucun PDF ni aucune relance ne sont générés.</p>
                <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal" :busy="form.processing">Enregistrer</AppButton><AppButton type="button" variant="secondaire" @click="formOpen = false">Annuler</AppButton></div>
            </form>

            <section class="grid min-w-0 gap-4" aria-labelledby="receivables-title">
                <div class="flex min-w-0 flex-wrap items-end justify-between gap-3"><div><h2 id="receivables-title" class="text-section-title">Créances échues</h2><p class="text-sm text-ink-secondary">Montant restant et ancienneté calculés à la date du jour.</p></div><label class="grid gap-1 text-sm font-semibold">Trier<select class="touch-target rounded-lg border border-separator px-3" :value="filters.sort || 'age_desc'" @change="filter('sort', $event.target.value)"><option value="age_desc">Plus anciennes</option><option value="outstanding_desc">Montant restant</option></select></label></div>
                <EmptyState v-if="receivables.length === 0" title="Aucune créance échue." reason="Toutes les factures arrivées à échéance sont soldées ou annulées." />
                <ul v-else class="grid min-w-0 gap-3 sm:grid-cols-2"><li v-for="invoice in receivables" :key="invoice.id" class="grid min-w-0 gap-2 rounded-xl border border-warning bg-warning-soft p-4"><strong>{{ invoice.number }} · {{ invoice.client_name }}</strong><span>Reste dû : {{ invoice.outstanding_amount_label }}</span><span>Ancienneté : {{ invoice.age_label }}</span></li></ul>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="invoices-title">
                <div class="flex flex-wrap items-end justify-between gap-3"><h2 id="invoices-title" class="text-section-title">Toutes les factures</h2><label class="grid gap-1 text-sm font-semibold">État<select class="touch-target rounded-lg border border-separator px-3" :value="filters.state || ''" @change="filter('state', $event.target.value)"><option value="">Tous</option><option value="impayee">Impayée</option><option value="partiellement_payee">Partiellement payée</option><option value="payee">Payée</option><option value="annulee">Annulée</option></select></label></div>
                <EmptyState v-if="invoices.length === 0" title="Aucune facture." reason="Créez la première facture depuis un contrat actif." />
                <ul v-else class="grid min-w-0 gap-4"><li v-for="invoice in invoices" :key="invoice.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4"><div class="flex min-w-0 flex-wrap justify-between gap-2"><div><strong>{{ invoice.number }}</strong><p>{{ invoice.client_name }} · {{ invoice.contract_reference }}</p></div><StatusBadge :status="invoice.state" /></div><dl class="grid gap-2 sm:grid-cols-3"><div><dt class="text-sm text-ink-secondary">Montant</dt><dd class="font-bold">{{ invoice.total_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Reste dû</dt><dd class="font-bold">{{ invoice.outstanding_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Échéance</dt><dd>{{ invoice.due_on }}</dd></div></dl><AppButton v-if="invoice.state === 'impayee'" variant="danger" @click="cancelInvoice = invoice">Annuler avec motif</AppButton></li></ul>
            </section>

            <form v-if="cancelInvoice" class="fixed inset-x-3 bottom-3 z-30 grid gap-3 rounded-xl border border-danger bg-surface p-4 shadow-xl sm:left-auto sm:max-w-md" @submit.prevent="confirmCancellation"><h2 class="font-bold">Annuler {{ cancelInvoice.number }} sans la supprimer</h2><label class="grid gap-2 font-semibold">Motif<textarea v-model="cancelForm.reason" class="min-h-24 rounded-lg border border-separator p-3" required minlength="10" /></label><p v-if="cancelForm.errors.reason" class="text-sm font-semibold text-danger">⚠ {{ cancelForm.errors.reason }}</p><div class="flex flex-wrap gap-2"><AppButton type="submit" variant="danger" :busy="cancelForm.processing">Confirmer l’annulation</AppButton><AppButton type="button" variant="secondaire" @click="cancelInvoice = null">Retour</AppButton></div></form>
        </div>
    </AppLayout>
</template>
