<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AttachmentUploader from '../../../Components/AttachmentUploader.vue';

const props = defineProps({
    expense: { type: Object, required: true },
});

const cancelForm = useForm({ cancel_reason: '' });
const cancelFormOpen = ref(false);

function openCancelForm() {
    cancelFormOpen.value = true;
}

function submitCancel() {
    cancelForm.patch(`/depenses/${props.expense.id}/annuler`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelForm.reset();
            cancelFormOpen.value = false;
        },
    });
}

const canCancel = computed(() => props.expense.can_cancel);
const isPending = computed(() => props.expense.state === 'demandee');
const isCancelled = computed(() => props.expense.state === 'annulee');
</script>

<template>
    <Head :title="`Demande de dépense — ${expense.reason}`" />

    <AppLayout :title="`Demande de dépense`" back-label="Retour au registre" back-href="/depenses" active-navigation="settings">
        <div class="grid min-w-0 gap-6">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">{{ expense.reason }}</h1>
                    <p class="max-w-2xl text-ink-secondary">Détails de la demande de dépense.</p>
                </div>
                <StatusBadge :status="expense.state" />
            </header>

            <div class="grid min-w-0 gap-6 rounded-xl border border-separator bg-surface p-6">
                <h2 class="text-section-title">Informations de la demande</h2>

                <div class="grid gap-4 text-sm">
                    <div class="grid gap-1">
                        <p class="font-semibold text-ink-secondary">Montant</p>
                        <p class="text-lg font-semibold text-primary">{{ expense.formatted_amount }}</p>
                    </div>

                    <div class="grid gap-1">
                        <p class="font-semibold text-ink-secondary">Bénéficiaire</p>
                        <p>{{ expense.beneficiary }}</p>
                    </div>

                    <div class="grid gap-1">
                        <p class="font-semibold text-ink-secondary">Catégorie</p>
                        <p>{{ expense.category.name }}</p>
                    </div>

                    <div class="grid gap-1">
                        <p class="font-semibold text-ink-secondary">Demandeur</p>
                        <p>{{ expense.requester.name }}</p>
                    </div>

                    <div class="grid gap-1">
                        <p class="font-semibold text-ink-secondary">Créée le</p>
                        <p>{{ expense.created_at }}</p>
                    </div>

                    <div v-if="expense.project_or_contract_note" class="grid gap-1">
                        <p class="font-semibold text-ink-secondary">Projet ou contrat</p>
                        <p>{{ expense.project_or_contract_note }}</p>
                    </div>

                    <div v-if="expense.cancel_reason" class="grid gap-1">
                        <p class="font-semibold text-danger">Motif d'annulation</p>
                        <p class="text-danger">{{ expense.cancel_reason }}</p>
                    </div>
                </div>
            </div>

            <div class="grid min-w-0 gap-6 rounded-xl border border-separator bg-surface p-6">
                <h2 class="text-section-title">Résultat attendu</h2>
                <p class="whitespace-pre-wrap text-sm">{{ expense.expected_result }}</p>
            </div>

            <div v-if="isPending && canCancel" class="grid min-w-0 gap-4">
                <AppButton v-if="!cancelFormOpen" variant="destructeur" @click="openCancelForm">Annuler cette demande</AppButton>

                <form v-if="cancelFormOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-primary-soft p-4" @submit.prevent="submitCancel">
                    <h2 class="text-section-title">Annulation de la demande</h2>

                    <FormField
                        id="cancel-reason"
                        v-model="cancelForm.cancel_reason"
                        label="Motif d'annulation"
                        type="textarea"
                        required
                        :error="cancelForm.errors.cancel_reason"
                        rows="3"
                        placeholder="Expliquez pourquoi cette demande est annulée..."
                    />

                    <div class="flex flex-wrap gap-2">
                        <AppButton type="submit" variant="destructeur" :busy="cancelForm.processing" busy-label="Annulation en cours">Confirmer l'annulation</AppButton>
                        <AppButton variant="secondaire" @click="cancelFormOpen = false">Ne pas annuler</AppButton>
                    </div>
                </form>
            </div>

            <EmptyState
                v-else-if="isCancelled"
                title="Cette demande a été annulée."
                :reason="`Motif : ${expense.cancel_reason}`"
            />

            <EmptyState
                v-else-if="!isPending"
                title="Cette demande ne peut plus être modifiée."
                reason="Une décision a déjà été prise."
            />
        </div>
    </AppLayout>
</template>
