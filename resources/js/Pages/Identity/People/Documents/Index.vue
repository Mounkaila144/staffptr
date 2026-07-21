<script setup>
import { nextTick, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../../Components/AppButton.vue';
import AttachmentUploader from '../../../../Components/AttachmentUploader.vue';
import EmptyState from '../../../../Components/EmptyState.vue';
import StatusBadge from '../../../../Components/StatusBadge.vue';
import AppLayout from '../../../../Layouts/AppLayout.vue';

const props = defineProps({
    person: { type: Object, required: true },
    documents: { type: Array, required: true },
    documentTypes: { type: Array, required: true },
    canDeposit: { type: Boolean, required: true },
    attachmentAllowedTypes: { type: Array, required: true },
    attachmentMaxSizeBytes: { type: Number, required: true },
});

const documentTypeField = ref(null);
const selectedType = ref('');
const stagedAttachment = ref(null);
const activeArchiveId = ref(null);
const archiveReasons = reactive({});
const archiveErrors = reactive({});
const archivingId = ref(null);
const depositForm = useForm({ document_type: '', attachment_ulid: '' });

function focusDeposit() {
    nextTick(() => documentTypeField.value?.focus());
}

function deposit() {
    if (!stagedAttachment.value || !selectedType.value) {
        focusDeposit();
        return;
    }

    depositForm.document_type = selectedType.value;
    depositForm.attachment_ulid = stagedAttachment.value.ulid;
    depositForm.post(`/personnes/${props.person.id}/documents`, {
        preserveScroll: true,
        onError: () => nextTick(() => {
            const target = depositForm.errors.document_type ? documentTypeField.value : document.querySelector('#attachment-file');
            target?.focus?.();
        }),
    });
}

function attachmentUploaded(attachment) {
    stagedAttachment.value = attachment;
    deposit();
}

function openArchive(document) {
    activeArchiveId.value = document.id;
    archiveReasons[document.id] = archiveReasons[document.id] ?? '';
    archiveErrors[document.id] = '';
    nextTick(() => document.getElementById(`archive-reason-${document.id}`)?.focus());
}

function archive(document) {
    archivingId.value = document.id;
    archiveErrors[document.id] = '';
    router.patch(
        `/personnes/${props.person.id}/documents/${document.id}/archiver`,
        { archive_reason: archiveReasons[document.id] ?? '' },
        {
            preserveScroll: true,
            onError: (errors) => {
                archiveErrors[document.id] = errors.archive_reason ?? "Le document n'a pas pu être archivé.";
                nextTick(() => document.getElementById(`archive-reason-${document.id}`)?.focus());
            },
            onFinish: () => { archivingId.value = null; },
        },
    );
}
</script>

<template>
    <AppLayout :title="`Documents de ${person.name}`" :back-href="`/personnes/${person.id}`" back-label="Retour à la fiche" active-navigation="team">
        <div class="grid min-w-0 gap-8">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Documents du dossier personnel</h1>
                <p class="break-words text-ink-secondary">{{ person.name }}</p>
            </header>

            <section v-if="canDeposit" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="deposit-title">
                <div class="grid gap-1">
                    <h2 id="deposit-title" class="text-section-title">Ranger un document</h2>
                    <p class="text-sm text-ink-secondary">Choisissez son type avant de téléverser le fichier privé.</p>
                </div>
                <label class="grid min-w-0 gap-2 font-semibold" for="document_type">
                    Type de document
                    <select id="document_type" ref="documentTypeField" v-model="selectedType" name="document_type" required class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                        <option value="">Choisir un type</option>
                        <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                    <span v-if="depositForm.errors.document_type" class="text-sm font-semibold text-danger">⚠ {{ depositForm.errors.document_type }}</span>
                </label>
                <AttachmentUploader
                    v-if="selectedType"
                    attachable-type="person"
                    :attachable-id="person.id"
                    :allowed-types="attachmentAllowedTypes"
                    :max-size-bytes="attachmentMaxSizeBytes"
                    @uploaded="attachmentUploaded"
                />
                <p v-if="depositForm.errors.attachment_ulid" class="text-sm font-semibold text-danger">⚠ {{ depositForm.errors.attachment_ulid }}</p>
                <AppButton v-if="stagedAttachment && depositForm.hasErrors" variant="principal" :busy="depositForm.processing" busy-label="Classement en cours" @click="deposit">Réessayer de ranger le fichier</AppButton>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="documents-title">
                <h2 id="documents-title" class="text-section-title">Documents conservés</h2>
                <EmptyState
                    v-if="documents.length === 0"
                    title="Aucun document dans ce dossier."
                    reason="Les contrats, conventions, fiches de poste et engagements signés apparaîtront ici."
                    :action-label="canDeposit ? 'Ranger un document' : ''"
                    @action="focusDeposit"
                />

                <div v-else class="grid min-w-0 gap-4">
                    <article v-for="document in documents" :key="document.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <img v-if="document.thumbnail_url" :src="document.thumbnail_url" alt="" width="64" height="64" class="size-16 shrink-0 rounded-lg object-cover">
                                <div class="grid min-w-0 gap-1">
                                    <h3 class="break-words font-semibold">{{ document.type_label }}</h3>
                                    <p class="break-words text-sm text-ink-secondary">{{ document.original_name ?? 'Fichier indisponible' }}</p>
                                </div>
                            </div>
                            <StatusBadge v-if="document.archived" status="archive" />
                        </div>

                        <p class="break-words text-sm text-ink-secondary">Déposé le {{ document.created_at }} par {{ document.author }}</p>
                        <div v-if="document.archived" class="grid gap-1 rounded-lg border border-separator bg-neutral-soft p-3 text-sm">
                            <p class="font-semibold">Document archivé le {{ document.archived_at }}</p>
                            <p class="break-words"><span class="font-semibold">Motif :</span> {{ document.archive_reason }}</p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Link :href="`/personnes/${person.id}/documents/${document.id}`" class="touch-target inline-flex items-center justify-center rounded-lg bg-primary px-4 font-semibold text-white">
                                Consulter le document
                            </Link>
                            <AppButton v-if="canDeposit && !document.archived" variant="destructeur" @click="openArchive(document)">Archiver</AppButton>
                        </div>

                        <form v-if="activeArchiveId === document.id && !document.archived" class="grid min-w-0 gap-3 rounded-lg border border-separator p-3" @submit.prevent="archive(document)">
                            <label :for="`archive-reason-${document.id}`" class="grid min-w-0 gap-2 font-semibold">
                                Motif de l’archivage
                                <textarea :id="`archive-reason-${document.id}`" v-model="archiveReasons[document.id]" name="archive_reason" rows="3" required minlength="10" maxlength="1000" class="min-w-0 rounded-lg border border-separator bg-surface p-3 font-normal" />
                                <span v-if="archiveErrors[document.id]" class="text-sm font-semibold text-danger">⚠ {{ archiveErrors[document.id] }}</span>
                            </label>
                            <div class="flex flex-wrap gap-3">
                                <AppButton type="submit" variant="destructeur" :busy="archivingId === document.id" busy-label="Archivage en cours">Confirmer l’archivage</AppButton>
                                <AppButton variant="discret" @click="activeArchiveId = null">Annuler</AppButton>
                            </div>
                        </form>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
