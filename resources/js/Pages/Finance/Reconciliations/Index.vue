<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({ reconciliations: { type: Array, required: true }, accounts: { type: Array, required: true }, responsibles: { type: Array, required: true } });
const form = useForm({ account_id: '', period_start: '', period_end: '', physical_balance_amount: 0, difference_explanation: '', responsible_id: null, corrective_action: '' });
function submit() { form.post('/finances/rapprochements', { preserveScroll: true }); }
function validateItem(id) { useForm({}).patch(`/finances/rapprochements/${id}/valider`, { preserveScroll: true }); }
</script>

<template>
    <AppLayout title="Rapprochements" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2"><h1 class="text-page-title">Rapprochement hebdomadaire</h1><p class="text-ink-secondary">Saisie du solde physique par compte, utilisable depuis la caisse sur téléphone.</p></header>
            <form class="grid gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <label class="grid gap-2 font-semibold">Compte<select v-model="form.account_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.label }}</option></select></label>
                <div class="grid gap-3 sm:grid-cols-2"><FormField id="reconciliation-start" v-model="form.period_start" type="date" label="Début" required /><FormField id="reconciliation-end" v-model="form.period_end" type="date" label="Fin" required /></div>
                <FormField id="reconciliation-physical" v-model="form.physical_balance_amount" type="number" min="0" label="Solde physique constaté en XOF" required />
                <FormField id="reconciliation-explanation" v-model="form.difference_explanation" label="Explication si écart" />
                <label class="grid gap-2 font-semibold">Responsable si écart<select v-model="form.responsible_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Non défini</option><option v-for="user in responsibles" :key="user.id" :value="user.id">{{ user.person?.full_name ?? `Compte #${user.id}` }}</option></select></label>
                <FormField id="reconciliation-action" v-model="form.corrective_action" label="Action corrective si écart" />
                <AppButton type="submit" variant="principal" :busy="form.processing">Préparer et calculer l'écart</AppButton>
            </form>
            <EmptyState v-if="reconciliations.length === 0" title="Aucun rapprochement enregistré." reason="Préparez le premier rapprochement des deux comptes." />
            <ul v-else class="grid gap-3"><li v-for="item in reconciliations" :key="item.id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4"><strong>{{ item.account }} · {{ item.period_start }} au {{ item.period_end }}</strong><p>Solde calculé : {{ item.calculated_balance_label }}</p><p>Solde physique : {{ item.physical_balance_label }}</p><p class="font-bold">Écart affiché : {{ item.difference_label }}</p><p v-if="item.difference_explanation">{{ item.difference_explanation }} · Action : {{ item.corrective_action }}</p><AppButton v-if="item.state === 'draft'" variant="secondaire" @click="validateItem(item.id)">Contrôler et figer</AppButton></li></ul>
        </div>
    </AppLayout>
</template>
