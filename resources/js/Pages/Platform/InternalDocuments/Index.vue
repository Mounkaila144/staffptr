<script setup>
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AttachmentUploader from '../../../Components/AttachmentUploader.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    documents: { type: Array, required: true },
    canManage: { type: Boolean, required: true },
    uploaderPersonId: { type: Number, required: true },
    attachmentAllowedTypes: { type: Array, required: true },
    attachmentMaxSizeBytes: { type: Number, required: true },
});

const publishing = ref(false);
const now = new Date();
const localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const form = useForm({
    title: '',
    requires_acknowledgement: true,
    body: '',
    effective_date: localDate,
    attachment_ulid: null,
});

function submit() {
    form.post('/documents-internes', {
        onSuccess: () => form.reset(),
        onError: () => nextTick(() => document.querySelector('[aria-invalid="true"]')?.focus()),
    });
}
</script>

<template>
    <AppLayout title="Documents internes" back-label="Accueil" active-navigation="documents">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Règles et engagements internes</h1>
                    <p class="text-ink-secondary">Consultez les versions applicables et confirmez leur acceptation lorsque cela est demandé.</p>
                </div>
                <AppButton v-if="canManage" variant="principal" @click="publishing = !publishing">
                    {{ publishing ? 'Fermer le formulaire' : 'Publier un document' }}
                </AppButton>
            </header>

            <form v-if="canManage && publishing" class="grid min-w-0 gap-5 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">Nouvelle publication</h2>
                <FormField id="internal-document-title" v-model="form.title" label="Titre" :error="form.errors.title" required />
                <FormField id="internal-document-effective-date" v-model="form.effective_date" label="Date d’application" variant="date" :error="form.errors.effective_date" required />
                <label class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                    <input v-model="form.requires_acknowledgement" type="checkbox">
                    Exiger un accusé de lecture et d’acceptation
                </label>
                <label class="grid min-w-0 gap-2" for="internal-document-body">
                    <span class="font-semibold">Contenu du document</span>
                    <textarea id="internal-document-body" v-model="form.body" rows="12" class="min-w-0 resize-y rounded-lg border border-separator bg-surface p-3 text-base" :aria-invalid="form.errors.body ? 'true' : 'false'" :aria-describedby="form.errors.body ? 'internal-document-body-error' : 'internal-document-body-help'" />
                    <span id="internal-document-body-help" class="text-sm text-ink-secondary">Saisissez le contenu, ajoutez un fichier, ou fournissez les deux.</span>
                    <span v-if="form.errors.body" id="internal-document-body-error" class="text-sm font-semibold text-danger">⚠ {{ form.errors.body }}</span>
                </label>
                <AttachmentUploader
                    :attachable-id="uploaderPersonId"
                    :allowed-types="attachmentAllowedTypes"
                    :max-size-bytes="attachmentMaxSizeBytes"
                    @uploaded="form.attachment_ulid = $event.ulid"
                />
                <p v-if="form.errors.attachment_ulid" class="text-sm font-semibold text-danger">⚠ {{ form.errors.attachment_ulid }}</p>
                <AppButton type="submit" variant="principal" :busy="form.processing" busy-label="Publication en cours">Publier la version 1</AppButton>
            </form>

            <EmptyState
                v-if="documents.length === 0"
                title="Aucun document interne publié."
                reason="La direction publiera ici les règles et engagements applicables."
                :action-label="canManage ? 'Publier un document' : ''"
                @action="publishing = true"
            />

            <ul v-else class="grid min-w-0 gap-4 sm:grid-cols-2">
                <li v-for="document in documents" :key="document.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                        <h2 class="min-w-0 break-words text-card-title">{{ document.title }}</h2>
                        <StatusBadge :status="!document.requires_acknowledgement || document.acknowledged ? 'validé' : 'en attente'" />
                    </div>
                    <p class="text-sm text-ink-secondary">Version {{ document.version_number }} · applicable le {{ document.effective_date }}</p>
                    <p v-if="document.requires_acknowledgement" class="text-sm">{{ document.acknowledged ? 'Vous avez accepté cette version.' : 'Votre acceptation est attendue.' }}</p>
                    <p v-else class="text-sm">Aucun accusé d’acceptation n’est demandé.</p>
                    <a :href="`/documents-internes/${document.id}`" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">Lire le document</a>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
