<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({ reconciliations: { type: Array, required: true }, accounts: { type: Array, required: true }, responsibles: { type: Array, required: true } });
const form = useForm({ account_id: '', period_start: '', period_end: '', physical_balance_amount: 0, difference_explanation: '', responsible_id: null, corrective_action: '', correction_reason: '' });
// Correction : le formulaire est prérempli avec le rapprochement choisi ; l’envoi crée un nouveau
// rapprochement rattaché au précédent, avec son motif (l’original n’est jamais modifié).
const correcting = ref(null);
const formElement = ref(null);
async function startCorrection(item) {
    correcting.value = item;
    Object.assign(form, {
        account_id: item.account_id, period_start: item.period_start, period_end: item.period_end, physical_balance_amount: item.physical_balance_amount,
        difference_explanation: item.difference_explanation ?? '', responsible_id: item.responsible_id, corrective_action: item.corrective_action ?? '', correction_reason: '',
    });
    form.clearErrors();
    await nextTick();
    formElement.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function stopCorrection() { correcting.value = null; form.reset(); form.clearErrors(); }
function submit() {
    const url = correcting.value ? `/finances/rapprochements/${correcting.value.id}/corriger` : '/finances/rapprochements';
    form.post(url, { preserveScroll: true, onSuccess: stopCorrection });
}
// Les refus du contrôle (même personne que le préparateur, écart non expliqué) s’affichent sur la fiche.
const validation = useForm({});
const validatingId = ref(null);
function validateItem(id) {
    validatingId.value = id;
    validation.clearErrors().patch(`/finances/rapprochements/${id}/valider`, { preserveScroll: true });
}
function validationError(id) {
    if (validatingId.value !== id) return '';
    return Object.values(validation.errors).join(' ');
}
// Un rapprochement corrigé reste consultable mais n’est plus à contrôler : c’est sa correction qui l’est.
const replacedIds = computed(() => new Set(props.reconciliations.map((item) => item.previous_id).filter(Boolean)));
function stateLabel(item) {
    if (replacedIds.value.has(item.id)) return 'Remplacé par une correction';
    return ({ draft: 'À contrôler', validated: 'Contrôlé et figé', corrected: 'Remplacé par une correction' })[item.state] ?? item.state;
}
function frenchDate(iso) { return iso ? String(iso).slice(0, 10).split('-').reverse().join('/') : ''; }
</script>

<template>
    <AppLayout title="Rapprochements" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2"><h1 class="text-page-title">Rapprochement hebdomadaire</h1><p class="text-ink-secondary">Saisie du solde physique par compte, utilisable depuis la caisse sur téléphone.</p></header>
            <form ref="formElement" class="grid gap-4 rounded-xl border bg-surface p-4" :class="correcting ? 'border-warning' : 'border-separator'" @submit.prevent="submit">
                <p v-if="correcting" class="rounded-lg bg-warning-soft p-3 text-sm font-semibold">Correction du rapprochement {{ correcting.account }} du {{ frenchDate(correcting.period_start) }} au {{ frenchDate(correcting.period_end) }} : un nouveau rapprochement sera créé et rattaché à l’ancien.</p>
                <label class="grid gap-2 font-semibold">Compte<select v-model="form.account_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.label }}</option></select></label>
                <p v-if="form.errors.account_id" class="text-sm font-semibold text-danger">⚠ {{ form.errors.account_id }}</p>
                <div class="grid gap-3 sm:grid-cols-2"><FormField id="reconciliation-start" v-model="form.period_start" variant="date" label="Début" required :error="form.errors.period_start" /><FormField id="reconciliation-end" v-model="form.period_end" variant="date" label="Fin" required :error="form.errors.period_end" /></div>
                <FormField id="reconciliation-physical" v-model="form.physical_balance_amount" variant="money" label="Solde physique constaté en XOF" required :error="form.errors.physical_balance_amount" />
                <FormField id="reconciliation-explanation" v-model="form.difference_explanation" label="Explication si écart" :error="form.errors.difference_explanation" />
                <label class="grid gap-2 font-semibold">Responsable si écart<select v-model="form.responsible_id" class="touch-target rounded-lg border border-separator px-3"><option :value="null">Non défini</option><option v-for="user in responsibles" :key="user.id" :value="user.id">{{ user.person?.full_name ?? `Compte #${user.id}` }}</option></select></label>
                <FormField id="reconciliation-action" v-model="form.corrective_action" label="Action corrective si écart" :error="form.errors.corrective_action" />
                <FormField v-if="correcting" id="reconciliation-correction-reason" v-model="form.correction_reason" label="Motif de correction" required :error="form.errors.correction_reason" />
                <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal" :busy="form.processing">{{ correcting ? 'Enregistrer la correction' : "Préparer et calculer l'écart" }}</AppButton><AppButton v-if="correcting" type="button" variant="secondaire" @click="stopCorrection">Abandonner la correction</AppButton></div>
            </form>
            <EmptyState v-if="reconciliations.length === 0" title="Aucun rapprochement enregistré." reason="Préparez le premier rapprochement des deux comptes." />
            <ul v-else class="grid gap-3"><li v-for="item in reconciliations" :key="item.id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4"><div class="flex flex-wrap items-start justify-between gap-2"><strong>{{ item.account }} · du {{ frenchDate(item.period_start) }} au {{ frenchDate(item.period_end) }}</strong><span class="rounded-full bg-neutral-soft px-3 py-1 text-sm font-semibold">{{ stateLabel(item) }}</span></div><p>Solde calculé : {{ item.calculated_balance_label }}</p><p>Solde physique : {{ item.physical_balance_label }}</p><p class="font-bold">Écart affiché : {{ item.difference_label }}</p><p v-if="item.difference_explanation">{{ item.difference_explanation }} · Action : {{ item.corrective_action }}</p><p v-if="item.state === 'draft' && !replacedIds.has(item.id)" class="text-sm text-ink-secondary">Le contrôle doit être fait par une autre personne que celle qui a préparé le rapprochement.</p><p v-if="validationError(item.id)" class="text-sm font-semibold text-danger" role="alert">⚠ {{ validationError(item.id) }}</p><p v-if="item.correction_reason" class="text-sm text-ink-secondary">Correction d’un rapprochement précédent — motif : {{ item.correction_reason }}</p><div class="flex flex-wrap gap-2"><AppButton v-if="item.state === 'draft' && !replacedIds.has(item.id)" variant="secondaire" :busy="validation.processing && validatingId === item.id" @click="validateItem(item.id)">Contrôler et figer</AppButton><AppButton v-if="!replacedIds.has(item.id)" variant="discret" @click="startCorrection(item)">Corriger<span class="sr-only"> le rapprochement {{ item.account }}</span></AppButton></div></li></ul>
        </div>
    </AppLayout>
</template>
