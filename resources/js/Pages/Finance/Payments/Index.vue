<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AttachmentUploader from '../../../Components/AttachmentUploader.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    payments: { type: Array, required: true }, clients: { type: Array, required: true }, contracts: { type: Array, required: true },
    projects: { type: Array, required: true }, invoices: { type: Array, required: true }, accounts: { type: Array, required: true },
    idempotencyKey: { type: String, required: true }, cancellationIdempotencyKey: { type: String, required: true }, attachment: { type: Object, required: true },
});
const formOpen = ref(false); const editing = ref(null); const cancelling = ref(null); const amountField = ref(null);
const form = useForm({ client_id: '', contract_id: null, project_id: null, invoice_id: null, account_id: '', received_amount: 0, received_at: '', payment_mode: 'especes', reference: '', attachment_ulid: null, idempotency_key: props.idempotencyKey, correction_reason: '' });
const cancelForm = useForm({ reason: '', idempotency_key: props.cancellationIdempotencyKey });
const visibleContracts = computed(() => props.contracts.filter((item) => Number(item.client_id) === Number(form.client_id)));
const visibleInvoices = computed(() => props.invoices.filter((item) => Number(item.client_id) === Number(form.client_id) && (!form.contract_id || Number(item.contract_id) === Number(form.contract_id))));

function localNow() { const date = new Date(); date.setMinutes(date.getMinutes() - date.getTimezoneOffset()); return date.toISOString().slice(0, 16); }
async function openForm(payment = null) {
    editing.value = payment;
    form.client_id = payment?.client_id ?? ''; form.contract_id = payment?.contract_id ?? null; form.project_id = payment?.project_id ?? null;
    form.invoice_id = payment?.invoice_id ?? null; form.account_id = payment?.account_id ?? ''; form.received_amount = payment?.received_amount ?? 0;
    form.received_at = payment?.received_at?.replace(' ', 'T') ?? localNow(); form.payment_mode = payment?.payment_mode ?? 'especes'; form.reference = payment?.reference ?? '';
    form.attachment_ulid = null; form.correction_reason = ''; form.idempotency_key = props.idempotencyKey; form.clearErrors(); formOpen.value = true; await nextTick(); amountField.value?.focus();
}
function submit() {
    const url = editing.value ? `/finances/encaissements/${editing.value.id}/corriger` : '/finances/encaissements';
    form.post(url, { preserveScroll: true, onSuccess: () => { formOpen.value = false; editing.value = null; form.reset(); } });
}
function cancelPayment() { cancelForm.post(`/finances/encaissements/${cancelling.value.id}/annuler`, { preserveScroll: true, onSuccess: () => { cancelling.value = null; cancelForm.reset(); } }); }
</script>

<template>
    <AppLayout title="Encaissements" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap justify-between gap-3"><div><h1 class="break-words text-page-title">Encaissements et reçus</h1><p class="text-ink-secondary">Chaque validation crédite le compte, impute le contrat, calcule les parts et la réserve dans une seule transaction.</p></div><AppButton variant="principal" @click="openForm()">Enregistrer un encaissement</AppButton></header>

            <form v-if="formOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">{{ editing ? `Corriger ${editing.receipt_number}` : 'Nouvel encaissement' }}</h2>
                <label class="grid gap-2 font-semibold">Client<select v-model="form.client_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option></select></label>
                <label class="grid gap-2 font-semibold">Contrat<select v-model="form.contract_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucun</option><option v-for="contract in visibleContracts" :key="contract.id" :value="contract.id">{{ contract.reference }} — {{ contract.title }}</option></select></label>
                <label class="grid gap-2 font-semibold">Projet si aucun contrat<select v-model="form.project_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucun</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
                <label class="grid gap-2 font-semibold">Facture facultative<select v-model="form.invoice_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucune</option><option v-for="invoice in visibleInvoices" :key="invoice.id" :value="invoice.id">{{ invoice.number }}</option></select></label>
                <label class="grid gap-2 font-semibold">Compte crédité<select v-model="form.account_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.label }}</option></select></label>
                <FormField ref="amountField" id="payment-amount" v-model="form.received_amount" type="number" min="1" label="Montant encaissé en XOF" required :error="form.errors.received_amount" />
                <FormField id="payment-received-at" v-model="form.received_at" type="datetime-local" label="Date et heure de réception" required :error="form.errors.received_at" />
                <label class="grid gap-2 font-semibold">Mode<select v-model="form.payment_mode" class="touch-target rounded-lg border border-separator px-3"><option value="especes">Espèces</option><option value="mobile_money">Mobile Money</option><option value="banque">Banque</option><option value="autre">Autre</option></select></label>
                <FormField id="payment-reference" v-model="form.reference" label="Référence facultative" :error="form.errors.reference" />
                <AttachmentUploader :attachable-id="attachment.attachable_id" :allowed-types="attachment.allowed_types" :max-size-bytes="attachment.max_size_bytes" @uploaded="form.attachment_ulid = $event.ulid" />
                <p v-if="form.errors.attachment_ulid" class="text-sm font-semibold text-danger">⚠ {{ form.errors.attachment_ulid }}</p>
                <label v-if="editing" class="grid gap-2 font-semibold">Motif de correction<textarea v-model="form.correction_reason" class="min-h-24 rounded-lg border border-separator p-3" required minlength="10" /></label>
                <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal" :busy="form.processing">Valider et attribuer le reçu</AppButton><AppButton type="button" variant="secondaire" @click="formOpen = false">Retour</AppButton></div>
            </form>

            <EmptyState v-if="payments.length === 0" title="Aucun encaissement enregistré." reason="Le premier reçu apparaîtra ici après validation atomique." />
            <ul v-else class="grid min-w-0 gap-4"><li v-for="payment in payments" :key="payment.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4"><div class="flex min-w-0 flex-wrap justify-between gap-2"><div><strong>{{ payment.receipt_number }}</strong><p>{{ payment.client_name }} · {{ payment.account_label }}</p></div><StatusBadge :status="payment.state" /></div><p class="text-xl font-bold">{{ payment.received_amount_label }}</p><p>{{ payment.received_at }} · {{ payment.payment_mode }}</p><p v-if="payment.late_recording" class="rounded-lg border border-warning bg-warning-soft p-3 font-semibold">Saisie tardive : plus de 24 h après la réception déclarée.</p><p class="text-sm">Justificatif privé : {{ payment.has_attachment ? 'rattaché' : 'absent' }}</p><div v-if="payment.state === 'validated' && !payment.reversal_of_id" class="flex flex-wrap gap-2"><AppButton variant="secondaire" @click="openForm(payment)">Corriger avec motif</AppButton><AppButton variant="danger" @click="cancelling = payment">Annuler par contre-écriture</AppButton></div></li></ul>

            <form v-if="cancelling" class="fixed inset-x-3 bottom-3 z-30 grid gap-3 rounded-xl border border-danger bg-surface p-4 shadow-xl sm:left-auto sm:max-w-md" @submit.prevent="cancelPayment"><h2 class="font-bold">Contre-écrire {{ cancelling.receipt_number }}</h2><label class="grid gap-2 font-semibold">Motif<textarea v-model="cancelForm.reason" class="min-h-24 rounded-lg border border-separator p-3" required minlength="10" /></label><p v-if="cancelForm.errors.reason" class="text-sm font-semibold text-danger">⚠ {{ cancelForm.errors.reason }}</p><div class="flex flex-wrap gap-2"><AppButton type="submit" variant="danger" :busy="cancelForm.processing">Créer la contre-écriture</AppButton><AppButton type="button" variant="secondaire" @click="cancelling = null">Retour</AppButton></div></form>
        </div>
    </AppLayout>
</template>
