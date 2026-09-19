<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({ reserve: { type: Object, required: true }, idempotencyKey: { type: String, required: true }, canApprove: { type: Boolean, required: true } });
const form = useForm({ movement_amount: 0, reason: '', reconstitution_plan: '', idempotency_key: props.idempotencyKey });
function submit() { form.post('/finances/reserve/utilisations', { preserveScroll: true }); }
function approve(id) { useForm({}).patch(`/finances/reserve/mouvements/${id}/approuver`, { preserveScroll: true }); }
</script>

<template>
    <AppLayout title="Réserve" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2"><h1 class="text-page-title">Réserve PTR Niger</h1><p class="text-ink-secondary">Livre auxiliaire calculé, sans saisie directe de solde.</p></header>
            <dl class="grid gap-3 sm:grid-cols-3"><div class="rounded-xl border border-separator p-4"><dt>Disponible</dt><dd class="text-card-title">{{ reserve.amount_label }}</dd></div><div class="rounded-xl border border-separator p-4"><dt>Objectif</dt><dd class="text-card-title">{{ reserve.objective_amount_label }}</dd></div><div class="rounded-xl border border-separator p-4"><dt>Couverture</dt><dd class="text-card-title">{{ reserve.covered_months }} mois / {{ reserve.target_months }}</dd></div></dl>
            <p class="rounded-lg bg-neutral-soft p-3 text-sm"><strong>Méthode :</strong> {{ reserve.method }} <strong>Date source :</strong> {{ reserve.source_date }}</p>
            <form class="grid gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">Demander une utilisation</h2>
                <FormField id="reserve-amount" v-model="form.movement_amount" type="number" min="1" label="Montant en XOF" required :error="form.errors.movement_amount" />
                <FormField id="reserve-reason" v-model="form.reason" label="Motif" required :error="form.errors.reason" />
                <FormField id="reserve-plan" v-model="form.reconstitution_plan" label="Plan de reconstitution" required :error="form.errors.reconstitution_plan" />
                <AppButton type="submit" variant="principal" :busy="form.processing">Soumettre aux deux approbations</AppButton>
            </form>
            <EmptyState v-if="reserve.movements.length === 0" title="Aucun mouvement de réserve." reason="Les allocations apparaîtront après les encaissements éligibles." />
            <ul v-else class="grid gap-3"><li v-for="movement in reserve.movements" :key="movement.id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4"><div class="flex flex-wrap justify-between gap-2"><strong>{{ movement.type }} · {{ movement.amount_label }}</strong><span>{{ movement.approval_state }}</span></div><p>{{ movement.reason }}</p><p v-if="movement.reconstitution_plan"><strong>Plan :</strong> {{ movement.reconstitution_plan }}</p><AppButton v-if="canApprove && movement.type === 'usage' && movement.approval_state === 'pending'" variant="secondaire" @click="approve(movement.id)">Approuver</AppButton></li></ul>
        </div>
    </AppLayout>
</template>
