<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({ reserve: { type: Object, required: true }, idempotencyKey: { type: String, required: true }, canApprove: { type: Boolean, required: true } });
const form = useForm({ movement_amount: '', reason: '', reconstitution_plan: '', idempotency_key: props.idempotencyKey });
// Clé renouvelée après chaque succès : sinon une seconde utilisation serait prise pour un doublon.
function submit() { form.post('/finances/reserve/utilisations', { preserveScroll: true, onSuccess: () => { form.reset(); form.idempotency_key = props.idempotencyKey; } }); }
// Libellés lisibles des valeurs techniques du livre de réserve.
const typeLabels = { allocation: 'Allocation', usage: 'Utilisation', reconstitution: 'Reconstitution', reversal: 'Contre-écriture' };
const stateLabels = { approved: 'Approuvé', pending: 'En attente d’approbation', refused: 'Refusé' };
function formatDate(iso) {
    const [year, month, day] = String(iso ?? '').slice(0, 10).split('-');

    return day ? `${day}/${month}/${year}` : iso;
}
function stateLabel(movement) {
    if (movement.approval_state === 'pending') {
        if ((movement.approvals_required ?? 2) === 1) {
            return 'En attente de l’approbation de l’autre dirigeant (demande d’un dirigeant : une seule suffit)';
        }
        return movement.first_approved_by ? 'En attente de la 2e approbation (1 sur 2)' : 'En attente de 2 approbations';
    }

    return stateLabels[movement.approval_state] ?? movement.approval_state;
}
const coveredMonthsLabel = computed(() => String(props.reserve.covered_months).replace('.', ','));
// Refus métier sans champ de formulaire (approbateurs non distincts, réserve insuffisante…).
const page = usePage();
const globalError = computed(() => page.props.errors?.reserve ?? page.props.errors?.approval ?? '');
function approve(id) { useForm({}).patch(`/finances/reserve/mouvements/${id}/approuver`, { preserveScroll: true }); }
</script>

<template>
    <AppLayout title="Réserve" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2"><h1 class="text-page-title">Réserve PTR Niger</h1><p class="text-ink-secondary">Livre auxiliaire calculé, sans saisie directe de solde.</p></header>
            <dl class="grid gap-3 sm:grid-cols-3"><div class="rounded-xl border border-separator p-4"><dt>Disponible</dt><dd class="text-card-title">{{ reserve.amount_label }}</dd></div><div class="rounded-xl border border-separator p-4"><dt>Objectif</dt><dd class="text-card-title">{{ reserve.objective_amount_label }}</dd></div><div class="rounded-xl border border-separator p-4"><dt>Couverture</dt><dd class="text-card-title">{{ coveredMonthsLabel }} mois / {{ reserve.target_months }}</dd></div></dl>
            <p class="rounded-lg bg-neutral-soft p-3 text-sm"><strong>Méthode :</strong> {{ reserve.method }} <strong>Date source :</strong> {{ formatDate(reserve.source_date) }}</p>
            <p v-if="globalError" class="rounded-xl border border-danger/40 bg-danger-soft p-4 font-semibold text-danger" role="alert">⚠ {{ globalError }}</p>
            <form class="grid gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">Demander une utilisation</h2>
                <FormField id="reserve-amount" v-model="form.movement_amount" variant="money" label="Montant (francs CFA)" required :error="form.errors.movement_amount" />
                <FormField id="reserve-reason" v-model="form.reason" label="Motif" required :error="form.errors.reason" />
                <FormField id="reserve-plan" v-model="form.reconstitution_plan" label="Plan de reconstitution" required :error="form.errors.reconstitution_plan" />
                <AppButton type="submit" variant="principal" :busy="form.processing">Soumettre à l’approbation de la direction</AppButton>
            </form>
            <EmptyState v-if="reserve.movements.length === 0" title="Aucun mouvement de réserve." reason="Les allocations apparaîtront après les encaissements éligibles." />
            <ul v-else class="grid gap-3"><li v-for="movement in reserve.movements" :key="movement.id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4"><div class="flex flex-wrap justify-between gap-2"><strong>{{ typeLabels[movement.type] ?? movement.type }} · {{ movement.amount_label }}</strong><span class="font-semibold">{{ stateLabel(movement) }}</span></div><p class="text-sm text-ink-secondary">Le {{ formatDate(movement.occurred_on) }}</p><p>{{ movement.reason }}</p><p v-if="movement.reconstitution_plan"><strong>Plan :</strong> {{ movement.reconstitution_plan }}</p><p v-if="movement.first_approver_name && movement.approval_state === 'pending'" class="text-sm text-ink-secondary">1re approbation : {{ movement.first_approver_name }}</p><AppButton v-if="movement.can_approve" variant="secondaire" @click="approve(movement.id)">Approuver</AppButton></li></ul>
        </div>
    </AppLayout>
</template>
