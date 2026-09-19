<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AttachmentUploader from '../../../Components/AttachmentUploader.vue';

const props = defineProps({
    categories: { type: Array, required: true },
});

const form = useForm({
    category_id: '',
    reason: '',
    requested_amount: '',
    beneficiary: '',
    expected_result: '',
    project_or_contract_note: '',
    attachment_ulid: '',
});

const submitForm = () => {
    form.post('/depenses', {
        preserveScroll: true,
    });
};

// Format amount as user types
const formattedAmount = computed({
    get: () => form.requested_amount,
    set: (value) => {
        // Remove non-numeric characters
        const numeric = value.replace(/\D/g, '');
        form.requested_amount = numeric;
    },
});
</script>

<template>
    <Head title="Nouvelle demande de dépense" />

    <AppLayout title="Nouvelle demande de dépense" back-label="Retour au registre" back-href="/depenses" active-navigation="settings">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Nouvelle demande de dépense</h1>
                <p class="max-w-2xl text-ink-secondary">Enregistrez une demande de dépense avant tout paiement. Montant en XOF entier, sans décimale.</p>
            </header>

            <form class="grid min-w-0 gap-6" @submit.prevent="submitForm">
                <div class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-6">
                    <h2 class="text-section-title">Informations de la demande</h2>

                    <div class="grid gap-4">
                        <FormField
                            id="expense-category"
                            v-model="form.category_id"
                            label="Catégorie"
                            type="select"
                            required
                            :options="categories.map(c => ({ value: c.id, label: c.name }))"
                            :error="form.errors.category_id"
                        />

                        <FormField
                            id="expense-reason"
                            v-model="form.reason"
                            label="Motif de la dépense"
                            type="text"
                            required
                            :error="form.errors.reason"
                            placeholder="Ex: Achat de matériel pour le projet..."
                        />

                        <FormField
                            id="expense-amount"
                            v-model.number="form.requested_amount"
                            label="Montant (FCFA, entier)"
                            type="number"
                            required
                            min="1"
                            :error="form.errors.requested_amount"
                            placeholder="Ex: 50000"
                        />

                        <FormField
                            id="expense-beneficiary"
                            v-model="form.beneficiary"
                            label="Bénéficiaire"
                            type="text"
                            required
                            :error="form.errors.beneficiary"
                            placeholder="Nom du bénéficiaire ou fournisseur"
                        />

                        <FormField
                            id="expense-project"
                            v-model="form.project_or_contract_note"
                            label="Projet ou contrat (optionnel)"
                            type="text"
                            :error="form.errors.project_or_contract_note"
                            placeholder="Référence libre au projet ou contrat"
                        />
                    </div>
                </div>

                <div class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-6">
                    <h2 class="text-section-title">Résultat attendu</h2>

                    <FormField
                        id="expense-expected-result"
                        v-model="form.expected_result"
                        label="Décrivez le résultat attendu de cette dépense"
                        type="textarea"
                        required
                        rows="4"
                        :error="form.errors.expected_result"
                        placeholder="Ex: Permettre à l'équipe de terminer le livraison dans les délais..."
                    />
                </div>

                <div class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-6">
                    <h2 class="text-section-title">Justificatif (optionnel)</h2>

                    <AttachmentUploader
                        v-model="form.attachment_ulid"
                        :error="form.errors.attachment_ulid"
                        label="Justificatif prévisionnel (devis, facture proforma, etc.)"
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <AppButton type="submit" variant="principal" :busy="form.processing" busy-label="Création en cours">Créer la demande</AppButton>
                    <AppButton variant="secondaire" href="/depenses">Annuler</AppButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
