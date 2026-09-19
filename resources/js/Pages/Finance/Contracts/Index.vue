<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    contracts: { type: Array, required: true }, clients: { type: Array, required: true },
    projects: { type: Array, required: true }, contributors: { type: Array, required: true }, executors: { type: Array, required: true },
});

const formOpen = ref(false);
const editingId = ref(null);
const closingId = ref(null);
const closureReason = ref('');
const referenceField = ref(null);
const form = useForm({
    client_id: '', project_id: null, reference: '', title: '', expected_total_amount: 0,
    forecast_profit_amount: 0, contributor_id: null, has_execution: false, executor_ids: [], starts_on: '', ends_on: '',
});
const contributorSelected = computed(() => Boolean(form.contributor_id));

function personName(user) { return user.person?.full_name ?? `Compte #${user.id}`; }
function toggleExecutor(id) {
    form.executor_ids = form.executor_ids.includes(id) ? form.executor_ids.filter((value) => value !== id) : [...form.executor_ids, id];
}
async function openForm(contract = null) {
    editingId.value = contract?.id ?? null;
    for (const field of Object.keys(form.data())) form[field] = contract?.[field] ?? ({ has_execution: false, executor_ids: [], project_id: null, contributor_id: null }[field] ?? '');
    form.clearErrors(); formOpen.value = true; await nextTick(); referenceField.value?.focus();
}
function closeForm() { formOpen.value = false; editingId.value = null; form.reset(); }
function submit() {
    const options = { preserveScroll: true, onSuccess: closeForm, onError: () => nextTick(() => referenceField.value?.focus()) };
    if (editingId.value) form.patch(`/finances/contrats/${editingId.value}`, options); else form.post('/finances/contrats', options);
}
function closeContract(contract) {
    if (!closureReason.value.trim()) return;
    useForm({ closure_reason: closureReason.value }).post(`/finances/contrats/${contract.id}/cloturer`, {
        preserveScroll: true, onSuccess: () => { closingId.value = null; closureReason.value = ''; },
    });
}
</script>

<template>
    <AppLayout title="Contrats" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2"><h1 class="break-words text-page-title">Contrats et parts prévisionnelles</h1><p class="text-ink-secondary">Les parts sont calculées sur le bénéfice prévisionnel puis régularisées à la clôture.</p></div>
                <AppButton variant="principal" :disabled="clients.length === 0" @click="openForm()">Créer un contrat</AppButton>
            </header>

            <form v-if="formOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">{{ editingId ? 'Modifier le contrat' : 'Nouveau contrat' }}</h2>
                <label class="grid gap-2 font-semibold">Client<select v-model="form.client_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option></select></label>
                <label class="grid gap-2 font-semibold">Projet facultatif<select v-model="form.project_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucun projet</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
                <FormField ref="referenceField" id="contract-reference" v-model="form.reference" label="Référence" required :error="form.errors.reference" />
                <FormField id="contract-title" v-model="form.title" label="Intitulé" required :error="form.errors.title" />
                <FormField id="contract-total" v-model="form.expected_total_amount" label="Montant total prévu en XOF" type="number" min="0" required :error="form.errors.expected_total_amount" />
                <FormField id="contract-profit" v-model="form.forecast_profit_amount" label="Bénéfice prévisionnel en XOF" type="number" min="0" required :error="form.errors.forecast_profit_amount" />
                <label class="grid gap-2 font-semibold">Apporteur facultatif<select v-model="form.contributor_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucun apporteur</option><option v-for="user in contributors" :key="user.id" :value="user.id">{{ personName(user) }}</option></select></label>
                <label class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3"><input v-model="form.has_execution" type="checkbox"> Le contrat comporte une exécution</label>
                <fieldset v-if="form.has_execution && contributorSelected" class="grid gap-2 rounded-lg border border-separator p-3">
                    <legend class="px-1 font-semibold">Associés exécutants — part de 30 % répartie à parts égales</legend>
                    <label v-for="user in executors" :key="user.id" class="touch-target flex items-center gap-3"><input type="checkbox" :checked="form.executor_ids.includes(user.id)" @change="toggleExecutor(user.id)">{{ personName(user) }}</label>
                    <p v-if="form.errors.executor_ids" class="text-sm font-semibold text-danger">⚠ {{ form.errors.executor_ids }}</p>
                </fieldset>
                <div class="grid gap-3 sm:grid-cols-2"><FormField id="contract-start" v-model="form.starts_on" label="Début" type="date" :error="form.errors.starts_on" /><FormField id="contract-end" v-model="form.ends_on" label="Fin prévue" type="date" :error="form.errors.ends_on" /></div>
                <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal" :busy="form.processing">Enregistrer</AppButton><AppButton type="button" variant="secondaire" @click="closeForm">Annuler</AppButton></div>
            </form>

            <EmptyState v-if="contracts.length === 0" title="Aucun contrat enregistré." reason="Créez un contrat pour calculer sa répartition prévisionnelle." />
            <ul v-else class="grid min-w-0 gap-5">
                <li v-for="contract in contracts" :key="contract.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap justify-between gap-2"><div><p class="text-sm font-semibold text-ink-secondary">{{ contract.reference }} · {{ contract.client_name }}</p><h2 class="break-words text-card-title">{{ contract.title }}</h2></div><StatusBadge :status="contract.state" /></div>
                    <dl class="grid gap-3 sm:grid-cols-3"><div><dt class="text-sm text-ink-secondary">Montant total attendu</dt><dd class="font-bold">{{ contract.expected_total_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Total encaissé</dt><dd class="font-bold">{{ contract.received_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Bénéfice retenu</dt><dd class="font-bold">{{ contract.forecast_profit_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Bénéfice réalisé</dt><dd class="font-bold">{{ contract.actual_profit_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Coûts directs</dt><dd class="font-bold">{{ contract.direct_cost_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Écart</dt><dd class="font-bold">{{ contract.variance_amount_label }} · {{ contract.variance_percentage_label }}</dd></div></dl>
                    <p class="rounded-lg bg-neutral-soft p-3 text-sm"><strong>Méthode :</strong> {{ contract.calculation_method }}</p>
                    <ul class="grid gap-2"><li v-for="share in contract.shares" :key="`${share.beneficiary_key}-${share.share_type}`" class="grid gap-1 rounded-lg border border-separator p-3 sm:grid-cols-3"><strong>{{ share.beneficiary_name }} · {{ share.rate_label }}</strong><span>Droit matérialisé : {{ share.due_amount_label }}</span><span>Versé : {{ share.paid_amount_label }} · restant : {{ share.remaining_amount_label }}</span><span class="sm:col-span-3">Prévision : {{ share.forecast_amount_label }} · réalisé : {{ share.actual_amount_label }} ({{ share.variance_amount_label }})</span></li></ul>
                    <aside v-if="contract.regularization_proposal" class="rounded-lg border border-warning bg-warning-soft p-3"><strong>Proposition de régularisation à la clôture — {{ contract.regularization_proposal.amount_label }}</strong><p>{{ contract.regularization_proposal.message }}</p></aside>
                    <p v-else class="text-sm font-semibold text-success">Aucune régularisation à proposer.</p>
                    <div v-if="contract.state === 'active'" class="grid gap-3 sm:grid-cols-2">
                        <AppButton variant="secondaire" @click="openForm(contract)">Modifier le contrat</AppButton>
                        <AppButton variant="secondaire" @click="closingId = contract.id">Clôturer et calculer la régularisation</AppButton>
                    </div>
                    <form v-if="closingId === contract.id" class="grid gap-3 rounded-lg border border-warning p-3" @submit.prevent="closeContract(contract)">
                        <FormField :id="`closure-${contract.id}`" v-model="closureReason" label="Motif de clôture" required />
                        <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal">Confirmer la clôture</AppButton><AppButton type="button" variant="secondaire" @click="closingId = null">Annuler</AppButton></div>
                    </form>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
