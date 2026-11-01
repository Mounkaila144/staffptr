<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppModal from '../../../Components/AppModal.vue';
import AttachmentUploader from '../../../Components/AttachmentUploader.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    payments: { type: Array, required: true }, clients: { type: Array, required: true }, contracts: { type: Array, required: true },
    projects: { type: Array, required: true }, invoices: { type: Array, required: true }, accounts: { type: Array, required: true },
    idempotencyKey: { type: String, required: true }, cancellationIdempotencyKey: { type: String, required: true }, attachment: { type: Object, required: true },
});
const columns = [
    { key: 'receipt_number', label: 'Reçu', primary: true },
    { key: 'client_name', label: 'Client' },
    { key: 'received_amount_label', label: 'Montant', align: 'right' },
    { key: 'received_at', label: 'Reçu le' },
    { key: 'payment_mode', label: 'Mode', hideOnMobile: true },
    { key: 'has_attachment', label: 'Justificatif privé', hideOnMobile: true },
    { key: 'state', label: 'État' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const formOpen = ref(false); const editing = ref(null); const cancelling = ref(null); const amountField = ref(null);
const form = useForm({ client_id: '', contract_id: null, project_id: null, invoice_id: null, account_id: '', received_amount: 0, received_at: '', payment_mode: 'especes', reference: '', attachment_ulid: null, idempotency_key: props.idempotencyKey, correction_reason: '' });
const paymentModeLabels = { especes: 'Espèces', mobile_money: 'Mobile Money', banque: 'Banque', autre: 'Autre' };
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
// Après chaque contre-écriture, la page fournit une nouvelle clé : la réutiliser renverrait l’ancienne.
function cancelPayment() { cancelForm.post(`/finances/encaissements/${cancelling.value.id}/annuler`, { preserveScroll: true, onSuccess: () => { cancelling.value = null; cancelForm.reset(); cancelForm.idempotency_key = props.cancellationIdempotencyKey; } }); }
function openCancellation(payment) { cancelling.value = payment; cancelForm.reason = ''; cancelForm.clearErrors(); cancelForm.idempotency_key = props.cancellationIdempotencyKey; }
function frenchDateTime(value) { if (!value) return ''; const [date, time] = String(value).split(' '); return `${date.split('-').reverse().join('/')}${time ? ` à ${time}` : ''}`; }
const reversedReceipts = computed(() => Object.fromEntries(props.payments.map((item) => [item.id, item.receipt_number])));
</script>

<template>
    <AppLayout title="Encaissements" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <PageHeader title="Encaissements et reçus" description="Chaque validation crédite le compte, impute le contrat, calcule les parts et la réserve dans une seule transaction.">
                <template #actions><AppButton variant="principal" @click="openForm()">Enregistrer un encaissement</AppButton></template>
            </PageHeader>

            <AppModal :open="formOpen" size="lg" :title="editing ? `Corriger ${editing.receipt_number}` : 'Nouvel encaissement'" @update:open="(open) => !open && (formOpen = false)">
                <form class="grid min-w-0 gap-4" @submit.prevent="submit">
                <label class="grid gap-2 font-semibold">Client<select v-model="form.client_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option></select></label>
                <p v-if="form.errors.client_id" class="text-sm font-semibold text-danger">⚠ {{ form.errors.client_id }}</p>
                <label class="grid gap-2 font-semibold">Contrat<select v-model="form.contract_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucun</option><option v-for="contract in visibleContracts" :key="contract.id" :value="contract.id">{{ contract.reference }} — {{ contract.title }}</option></select></label>
                <p v-if="form.errors.contract_id" class="text-sm font-semibold text-danger">⚠ {{ form.errors.contract_id }}</p>
                <label class="grid gap-2 font-semibold">Projet si aucun contrat<select v-model="form.project_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucun</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
                <p v-if="form.errors.project_id" class="text-sm font-semibold text-danger">⚠ {{ form.errors.project_id }}</p>
                <label class="grid gap-2 font-semibold">Facture facultative<select v-model="form.invoice_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Aucune</option><option v-for="invoice in visibleInvoices" :key="invoice.id" :value="invoice.id">{{ invoice.number }}</option></select></label>
                <p v-if="form.errors.invoice_id" class="text-sm font-semibold text-danger">⚠ {{ form.errors.invoice_id }}</p>
                <label class="grid gap-2 font-semibold">Compte crédité<select v-model="form.account_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.label }}</option></select></label>
                <p v-if="form.errors.account_id" class="text-sm font-semibold text-danger">⚠ {{ form.errors.account_id }}</p>
                <FormField ref="amountField" id="payment-amount" v-model="form.received_amount" variant="money" label="Montant encaissé en XOF" required :error="form.errors.received_amount" />
                <FormField id="payment-received-at" v-model="form.received_at" variant="datetime-local" label="Date et heure de réception" required :error="form.errors.received_at" />
                <label class="grid gap-2 font-semibold">Mode<select v-model="form.payment_mode" class="touch-target rounded-lg border border-separator px-3"><option value="especes">Espèces</option><option value="mobile_money">Mobile Money</option><option value="banque">Banque</option><option value="autre">Autre</option></select></label>
                <FormField id="payment-reference" v-model="form.reference" label="Référence facultative" :error="form.errors.reference" />
                <AttachmentUploader :attachable-id="attachment.attachable_id" :allowed-types="attachment.allowed_types" :max-size-bytes="attachment.max_size_bytes" @uploaded="form.attachment_ulid = $event.ulid" />
                <p v-if="form.errors.attachment_ulid" class="text-sm font-semibold text-danger">⚠ {{ form.errors.attachment_ulid }}</p>
                <label v-if="editing" class="grid gap-2 font-semibold">Motif de correction<textarea v-model="form.correction_reason" class="min-h-24 rounded-lg border border-separator p-3" required minlength="10" /></label>
                <p v-if="form.errors.correction_reason" class="text-sm font-semibold text-danger">⚠ {{ form.errors.correction_reason }}</p>
                <p v-if="form.errors.payment" class="text-sm font-semibold text-danger">⚠ {{ form.errors.payment }}</p>
                <p v-if="form.errors.idempotency_key" class="text-sm font-semibold text-danger">⚠ {{ form.errors.idempotency_key }}</p>
                <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal" :busy="form.processing">Valider et attribuer le reçu</AppButton><AppButton type="button" variant="secondaire" @click="formOpen = false">Retour</AppButton></div>
            </form>
            </AppModal>

            <EmptyState v-if="payments.length === 0" title="Aucun encaissement enregistré." reason="Le premier reçu apparaîtra ici après validation atomique." />
            <DataTable v-else :columns="columns" :rows="payments" caption="Liste des encaissements">
                <template #cell-receipt_number="{ row }"><span class="grid gap-0.5"><span class="font-semibold text-ink">{{ row.receipt_number }}</span><span v-if="row.late_recording" class="text-xs font-semibold text-warning">⚠ Saisie tardive (plus de 24 h)</span></span></template>
                <template #cell-client_name="{ row }"><span class="grid gap-0.5"><span>{{ row.client_name }}</span><span class="text-xs text-ink-muted">{{ row.account_label }}</span></span></template>
                <template #cell-received_amount_label="{ row }"><span class="grid gap-0.5"><span class="font-semibold text-ink"><span v-if="row.reversal_of_id">−</span>{{ row.received_amount_label }}</span><span v-if="row.reversal_of_id" class="text-xs text-ink-secondary">Contre-écriture de {{ reversedReceipts[row.reversal_of_id] ?? 'un reçu' }}</span><span v-else-if="row.invoice_number" class="text-xs text-ink-muted">Facture {{ row.invoice_number }}</span></span></template>
                <template #cell-received_at="{ row }">{{ frenchDateTime(row.received_at) }}</template>
                <template #cell-payment_mode="{ row }">{{ paymentModeLabels[row.payment_mode] ?? row.payment_mode }}</template>
                <template #cell-has_attachment="{ row }"><a v-if="row.attachment_url" :href="row.attachment_url" target="_blank" rel="noopener" class="font-semibold text-primary underline">Ouvrir<span class="sr-only"> le justificatif du reçu {{ row.receipt_number }}</span></a><span v-else>Absent</span></template>
                <template #cell-state="{ row }"><StatusBadge :status="row.state" /></template>
                <template #cell-actions="{ row }"><span v-if="row.state === 'validated' && !row.reversal_of_id" class="inline-flex flex-wrap justify-end gap-1"><AppButton variant="discret" @click="openForm(row)">Corriger avec motif</AppButton><AppButton variant="discret" @click="openCancellation(row)">Annuler par contre-écriture</AppButton></span></template>
            </DataTable>

            <AppModal :open="Boolean(cancelling)" :title="`Contre-écrire ${cancelling?.receipt_number ?? ''}`" description="Une écriture inverse annule cet encaissement ; rien n’est supprimé." @update:open="(open) => !open && (cancelling = null)">
                <form id="payment-cancel-form" class="grid gap-3" @submit.prevent="cancelPayment"><label class="grid gap-2 font-semibold">Motif<textarea v-model="cancelForm.reason" class="min-h-24 rounded-lg border border-separator p-3 font-normal" required minlength="10" /></label><p v-if="cancelForm.errors.reason" class="text-sm font-semibold text-danger">⚠ {{ cancelForm.errors.reason }}</p><p v-if="cancelForm.errors.payment || cancelForm.errors.idempotency_key" class="text-sm font-semibold text-danger">⚠ {{ cancelForm.errors.payment || cancelForm.errors.idempotency_key }}</p></form>
                <template #footer><AppButton type="button" variant="secondaire" @click="cancelling = null">Retour</AppButton><AppButton type="submit" form="payment-cancel-form" variant="danger" :busy="cancelForm.processing">Créer la contre-écriture</AppButton></template>
            </AppModal>
        </div>
    </AppLayout>
</template>
