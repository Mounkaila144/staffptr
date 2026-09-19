<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AttachmentUploader from '../../../Components/AttachmentUploader.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    document: { type: Object, required: true },
    canManage: { type: Boolean, required: true },
    uploaderPersonId: { type: Number, required: true },
    attachmentAllowedTypes: { type: Array, required: true },
    attachmentMaxSizeBytes: { type: Number, required: true },
});

const showingVersionForm = ref(false);
const currentVersion = computed(() => props.document.versions.find((version) => version.is_current));
const acknowledgementForm = useForm({});
const now = new Date();
const localDate = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const versionForm = useForm({
    title: props.document.title,
    requires_acknowledgement: props.document.requires_acknowledgement,
    body: '',
    effective_date: localDate,
    attachment_ulid: null,
});

function acknowledge() {
    acknowledgementForm.post(`/documents-internes/${props.document.id}/accepter`, { preserveScroll: true });
}

function publishVersion() {
    versionForm.post(`/documents-internes/${props.document.id}/versions`, {
        onError: () => nextTick(() => document.querySelector('[aria-invalid="true"]')?.focus()),
    });
}
</script>

<template>
    <AppLayout :title="document.title" back-label="Documents" back-href="/documents-internes" active-navigation="documents">
        <div class="grid min-w-0 gap-8">
            <header class="grid min-w-0 gap-3">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <h1 class="min-w-0 break-words text-page-title">{{ document.title }}</h1>
                    <StatusBadge :status="!document.requires_acknowledgement || document.acknowledged ? 'validé' : 'en attente'" />
                </div>
                <p class="text-ink-secondary">Version {{ currentVersion.version_number }} · applicable le {{ currentVersion.effective_date }} · publiée le {{ currentVersion.published_at }}</p>
                <div v-if="canManage" class="flex flex-wrap gap-3">
                    <a :href="`/documents-internes/${document.id}/acceptations`" class="touch-target inline-flex items-center font-semibold text-primary underline-offset-4 hover:underline">Voir les acceptations</a>
                    <AppButton variant="secondaire" @click="showingVersionForm = !showingVersionForm">{{ showingVersionForm ? 'Fermer' : 'Publier une nouvelle version' }}</AppButton>
                </div>
            </header>

            <form v-if="canManage && showingVersionForm" class="grid min-w-0 gap-5 rounded-xl border border-separator bg-surface p-4" @submit.prevent="publishVersion">
                <h2 class="text-section-title">Nouvelle version</h2>
                <FormField id="version-title" v-model="versionForm.title" label="Titre" :error="versionForm.errors.title" required />
                <FormField id="version-effective-date" v-model="versionForm.effective_date" label="Date d’application" variant="date" :error="versionForm.errors.effective_date" required />
                <label class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                    <input v-model="versionForm.requires_acknowledgement" type="checkbox">
                    Exiger une nouvelle acceptation
                </label>
                <label class="grid min-w-0 gap-2" for="version-body">
                    <span class="font-semibold">Contenu de la nouvelle version</span>
                    <textarea id="version-body" v-model="versionForm.body" rows="12" class="min-w-0 resize-y rounded-lg border border-separator bg-surface p-3 text-base" :aria-invalid="versionForm.errors.body ? 'true' : 'false'" />
                    <span v-if="versionForm.errors.body" class="text-sm font-semibold text-danger">⚠ {{ versionForm.errors.body }}</span>
                </label>
                <AttachmentUploader
                    :attachable-id="uploaderPersonId"
                    :allowed-types="attachmentAllowedTypes"
                    :max-size-bytes="attachmentMaxSizeBytes"
                    @uploaded="versionForm.attachment_ulid = $event.ulid"
                />
                <p v-if="versionForm.errors.attachment_ulid" class="text-sm font-semibold text-danger">⚠ {{ versionForm.errors.attachment_ulid }}</p>
                <AppButton type="submit" variant="principal" :busy="versionForm.processing" busy-label="Publication en cours">Publier la nouvelle version</AppButton>
            </form>

            <article class="grid min-w-0 gap-6 rounded-xl border border-separator bg-surface p-4 sm:p-6" aria-labelledby="current-version-title">
                <h2 id="current-version-title" class="text-section-title">Version applicable</h2>
                <div v-if="currentVersion.body" class="min-w-0 whitespace-pre-wrap break-words text-base leading-7">{{ currentVersion.body }}</div>
                <p v-else class="text-ink-secondary">Le contenu de cette version est fourni dans le fichier joint.</p>
                <a v-if="currentVersion.attachment" :href="currentVersion.attachment.url" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">Ouvrir le fichier : {{ currentVersion.attachment.name }}</a>

                <footer class="grid gap-3 border-t border-separator pt-5">
                    <template v-if="document.requires_acknowledgement">
                        <p v-if="document.acknowledged" class="font-semibold text-success">✓ Vous avez accepté cette version.</p>
                        <form v-else class="grid gap-3" @submit.prevent="acknowledge">
                            <p>En confirmant, vous attestez avoir lu et accepté cette version du document.</p>
                            <p v-if="acknowledgementForm.errors.acknowledgement" class="text-sm font-semibold text-danger">⚠ {{ acknowledgementForm.errors.acknowledgement }}</p>
                            <AppButton type="submit" variant="principal" :busy="acknowledgementForm.processing" busy-label="Acceptation en cours">J’ai lu et j’accepte</AppButton>
                        </form>
                    </template>
                    <p v-else class="text-ink-secondary">Cette version ne demande pas d’accusé d’acceptation.</p>
                </footer>
            </article>

            <section class="grid min-w-0 gap-4" aria-labelledby="version-history-title">
                <h2 id="version-history-title" class="text-section-title">Historique complet des versions</h2>
                <details v-for="version in document.versions" :key="version.id" class="min-w-0 rounded-xl border border-separator bg-surface p-4" :open="version.is_current">
                    <summary class="touch-target cursor-pointer font-semibold">Version {{ version.version_number }} · {{ version.effective_date }}{{ version.is_current ? ' · applicable' : '' }}</summary>
                    <div class="grid min-w-0 gap-4 pt-4">
                        <p class="text-sm text-ink-secondary">Publiée le {{ version.published_at }} par {{ version.publisher }}</p>
                        <div v-if="version.body" class="min-w-0 whitespace-pre-wrap break-words leading-7">{{ version.body }}</div>
                        <a v-if="version.attachment" :href="version.attachment.url" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">Ouvrir {{ version.attachment.name }}</a>
                    </div>
                </details>
            </section>
        </div>
    </AppLayout>
</template>
