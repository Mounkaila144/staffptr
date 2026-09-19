<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    charges: { type: Array, required: true },
    currentBaseAmount: { type: Number, required: true },
    reserveObjectiveAmount: { type: Number, required: true },
    preview: { type: Object, default: null },
});

const formOpen = ref(false);
const editingId = ref(null);
const labelField = ref(null);
const form = useForm({
    fixed_charge_id: null,
    label: '',
    monthly_amount: 0,
    is_active: true,
    preview_token: '',
});

const previewIsCurrent = computed(() => props.preview
    && props.preview.fixed_charge_id === form.fixed_charge_id
    && props.preview.label === form.label.trim()
    && props.preview.monthly_amount === Number(form.monthly_amount)
    && props.preview.is_active === Boolean(form.is_active)
    && props.preview.token === form.preview_token);

watch(() => props.preview, (preview) => {
    if (!preview) return;
    formOpen.value = true;
    editingId.value = preview.fixed_charge_id;
    form.fixed_charge_id = preview.fixed_charge_id;
    form.label = preview.label;
    form.monthly_amount = preview.monthly_amount;
    form.is_active = preview.is_active;
    form.preview_token = preview.token;
}, { immediate: true });

async function openCreation() {
    editingId.value = null;
    form.reset();
    form.fixed_charge_id = null;
    form.is_active = true;
    formOpen.value = true;
    await nextTick();
    labelField.value?.focus();
}

async function openEdition(charge) {
    editingId.value = charge.id;
    form.fixed_charge_id = charge.id;
    form.label = charge.label;
    form.monthly_amount = charge.monthly_amount;
    form.is_active = charge.is_active;
    form.preview_token = '';
    form.clearErrors();
    formOpen.value = true;
    await nextTick();
    labelField.value?.focus();
}

function closeForm() {
    formOpen.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function requestPreview() {
    form.post('/finances/charges-fixes/apercu', {
        preserveScroll: true,
        onError: () => nextTick(() => labelField.value?.focus()),
    });
}

function confirm() {
    if (!previewIsCurrent.value) return;

    const options = {
        preserveScroll: true,
        onSuccess: closeForm,
        onError: () => nextTick(() => labelField.value?.focus()),
    };
    if (editingId.value) {
        form.patch(`/finances/charges-fixes/${editingId.value}`, options);
        return;
    }
    form.post('/finances/charges-fixes', options);
}

function formatAmount(amount) {
    return `${Number(amount).toLocaleString('fr-FR')} FCFA`;
}
</script>

<template>
    <AppLayout title="Charges fixes" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Charges fixes</h1>
                    <p class="max-w-2xl text-ink-secondary">Seules les charges actives constituent l’assiette de réserve et d’alerte. Les coûts directs des projets n’y entrent jamais.</p>
                </div>
                <AppButton variant="principal" @click="openCreation">Ajouter une charge</AppButton>
            </header>

            <section class="grid min-w-0 gap-3 rounded-xl border border-primary bg-primary-soft p-4 sm:grid-cols-2" aria-labelledby="fixed-charge-summary">
                <h2 id="fixed-charge-summary" class="text-section-title sm:col-span-2">Assiette actuelle</h2>
                <div><p class="text-sm text-ink-secondary">Charges fixes actives</p><p class="break-words text-xl font-bold">{{ formatAmount(currentBaseAmount) }}</p></div>
                <div><p class="text-sm text-ink-secondary">Objectif de réserve actuel</p><p class="break-words text-xl font-bold">{{ formatAmount(reserveObjectiveAmount) }}</p></div>
            </section>

            <form v-if="formOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-surface p-4" @submit.prevent="requestPreview">
                <h2 class="text-section-title">{{ editingId ? 'Modifier la charge' : 'Nouvelle charge fixe' }}</h2>
                <FormField ref="labelField" id="fixed-charge-label" v-model="form.label" label="Libellé" required :error="form.errors.label" />
                <FormField id="fixed-charge-amount" v-model="form.monthly_amount" label="Montant mensuel en XOF" type="number" min="0" required :error="form.errors.monthly_amount" />
                <label class="touch-target flex min-w-0 items-center gap-3 rounded-lg border border-separator px-3">
                    <input v-model="form.is_active" type="checkbox">
                    Charge active et incluse dans l’assiette
                </label>
                <p v-if="form.errors.is_active" class="text-sm font-semibold text-danger">⚠ {{ form.errors.is_active }}</p>

                <section v-if="previewIsCurrent" class="grid min-w-0 gap-2 rounded-lg border border-warning bg-warning-soft p-4" aria-live="polite">
                    <h3 class="font-bold">Impact avant confirmation</h3>
                    <p>{{ preview.impact.consequence }}</p>
                    <p class="text-sm text-ink-secondary">Assiette proposée : {{ formatAmount(preview.impact.proposed_base_amount) }} · {{ preview.impact.target_months }} mois de réserve.</p>
                </section>
                <p v-if="form.errors.preview_token" class="text-sm font-semibold text-danger">⚠ {{ form.errors.preview_token }}</p>

                <div class="flex flex-wrap gap-2">
                    <AppButton type="submit" variant="secondaire" :busy="form.processing" busy-label="Calcul en cours">Calculer l’impact</AppButton>
                    <AppButton v-if="previewIsCurrent" type="button" variant="principal" :busy="form.processing" @click="confirm">Confirmer</AppButton>
                    <AppButton type="button" variant="secondaire" @click="closeForm">Annuler</AppButton>
                </div>
            </form>

            <EmptyState
                v-if="charges.length === 0"
                title="Aucune charge fixe paramétrée."
                reason="Ajoutez les charges récurrentes pour calculer l’assiette et l’objectif de réserve."
                action-label="Ajouter une charge"
                @action="openCreation"
            />

            <ul v-else class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="charge in charges" :key="charge.id" class="grid min-w-0 content-start gap-3 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                        <h2 class="min-w-0 break-words text-card-title">{{ charge.label }}</h2>
                        <StatusBadge :status="charge.is_active ? 'actif' : 'inactif'" />
                    </div>
                    <p class="break-words text-xl font-bold">{{ charge.monthly_amount_label }} par mois</p>
                    <p class="text-sm font-semibold" :class="charge.is_active ? 'text-success' : 'text-ink-secondary'">{{ charge.is_active ? 'Incluse dans l’assiette' : 'Exclue de l’assiette' }}</p>
                    <AppButton variant="secondaire" @click="openEdition(charge)">Modifier et prévisualiser</AppButton>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
