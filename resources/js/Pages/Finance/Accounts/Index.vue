<script setup>
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    accounts: { type: Array, required: true },
    types: { type: Array, required: true },
});

const creationOpen = ref(false);
const labelField = ref(null);
const deactivatingId = ref(null);
const reasonField = ref(null);
const creationForm = useForm({
    type: 'caisse',
    label: '',
    opening_balance_amount: 0,
    opening_balance_date: new Date().toISOString().slice(0, 10),
});
const deactivationForm = useForm({ reason: '' });

async function openCreation() {
    creationOpen.value = true;
    await nextTick();
    labelField.value?.focus();
}

function submitCreation() {
    creationForm.post('/finances/comptes', {
        preserveScroll: true,
        onSuccess: () => {
            creationForm.reset();
            creationOpen.value = false;
        },
        onError: () => nextTick(() => labelField.value?.focus()),
    });
}

async function prepareDeactivation(account) {
    deactivatingId.value = account.id;
    deactivationForm.reset();
    await nextTick();
    reasonField.value?.focus();
}

function cancelDeactivation() {
    deactivatingId.value = null;
    deactivationForm.clearErrors();
}

function deactivate(account) {
    deactivationForm.patch(`/finances/comptes/${account.id}/desactiver`, {
        preserveScroll: true,
        onSuccess: cancelDeactivation,
        onError: () => nextTick(() => reasonField.value?.focus()),
    });
}

function typeLabel(value, types) {
    return types.find((type) => type.value === value)?.label ?? value;
}
</script>

<template>
    <AppLayout title="Comptes financiers" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Comptes financiers</h1>
                    <p class="max-w-2xl text-ink-secondary">Le solde courant est calculé depuis le solde initial et les écritures validées. Il n’est jamais saisi manuellement.</p>
                </div>
                <AppButton variant="principal" @click="openCreation">Créer un compte</AppButton>
            </header>

            <form v-if="creationOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-primary-soft p-4" @submit.prevent="submitCreation">
                <h2 class="text-section-title">Nouveau compte financier</h2>
                <label class="grid min-w-0 gap-2 font-semibold" for="financial-account-type">
                    Type
                    <select id="financial-account-type" v-model="creationForm.type" class="touch-target min-w-0 rounded-lg border border-separator bg-surface px-3">
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>
                <p v-if="creationForm.errors.type" class="text-sm font-semibold text-danger">⚠ {{ creationForm.errors.type }}</p>
                <FormField ref="labelField" id="financial-account-label" v-model="creationForm.label" label="Libellé" required :error="creationForm.errors.label" />
                <FormField id="financial-account-opening-balance" v-model="creationForm.opening_balance_amount" label="Solde initial en XOF" type="number" min="0" required :error="creationForm.errors.opening_balance_amount" />
                <FormField id="financial-account-opening-date" v-model="creationForm.opening_balance_date" label="Date du solde initial" type="date" required :error="creationForm.errors.opening_balance_date" />
                <div class="flex flex-wrap gap-2">
                    <AppButton type="submit" variant="principal" :busy="creationForm.processing" busy-label="Création en cours">Créer</AppButton>
                    <AppButton type="button" variant="secondaire" @click="creationOpen = false">Annuler</AppButton>
                </div>
            </form>

            <EmptyState
                v-if="accounts.length === 0"
                title="Aucun compte financier."
                reason="Créez le premier compte pour commencer à tracer les écritures et calculer son solde."
                action-label="Créer un compte"
                @action="openCreation"
            />

            <ul v-else class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="account in accounts" :key="account.id" class="grid min-w-0 content-start gap-4 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="break-words text-card-title">{{ account.label }}</h2>
                            <p class="text-sm text-ink-secondary">{{ typeLabel(account.type, types) }}</p>
                        </div>
                        <StatusBadge :status="account.state === 'active' ? 'actif' : 'inactif'" />
                    </div>
                    <dl class="grid min-w-0 gap-3">
                        <div>
                            <dt class="text-sm text-ink-secondary">Solde courant calculé</dt>
                            <dd class="break-words text-xl font-bold">{{ account.current_balance }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-secondary">Solde initial</dt>
                            <dd class="font-semibold">{{ account.opening_balance_amount.toLocaleString('fr-FR') }} FCFA au {{ account.opening_balance_date }}</dd>
                        </div>
                    </dl>
                    <p v-if="account.deactivation_reason" class="rounded-lg bg-neutral-soft p-3 text-sm"><strong>Motif de désactivation :</strong> {{ account.deactivation_reason }}</p>

                    <AppButton v-if="account.state === 'active' && deactivatingId !== account.id" variant="destructeur" @click="prepareDeactivation(account)">Désactiver</AppButton>
                    <form v-if="deactivatingId === account.id" class="grid min-w-0 gap-3 rounded-lg border border-danger p-3" @submit.prevent="deactivate(account)">
                        <label class="grid min-w-0 gap-2 font-semibold" :for="`financial-account-reason-${account.id}`">
                            Motif de désactivation
                            <textarea :id="`financial-account-reason-${account.id}`" ref="reasonField" v-model="deactivationForm.reason" class="min-h-24 min-w-0 rounded-lg border border-separator p-3" required />
                        </label>
                        <p v-if="deactivationForm.errors.reason" class="text-sm font-semibold text-danger">⚠ {{ deactivationForm.errors.reason }}</p>
                        <div class="flex flex-wrap gap-2">
                            <AppButton type="submit" variant="destructeur" :busy="deactivationForm.processing" busy-label="Désactivation en cours">Confirmer</AppButton>
                            <AppButton type="button" variant="secondaire" @click="cancelDeactivation">Annuler</AppButton>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
