<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({ task: Object, canUpdate: Boolean, attachment: Object });
const statusForm = useForm({ status: "", reason: "" });
const comment = useForm({ body: "" });

function changeStatus(status) {
    statusForm.status = status;
    statusForm.patch(`/taches/${props.task.id}/etat`, {
        preserveScroll: true,
        onSuccess: () => statusForm.reset(),
    });
}
const link = useForm({ label: "", url: "" });
const file = useForm({ attachment_ulid: "" });
// Chaque formulaire se vide après un enregistrement réussi.
const send = (form, url) => form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
</script>
<template>
    <Head :title="task.title" /><AppLayout
        title="Tâche"
        back-label="Retour aux tâches"
        back-href="/taches"
        ><article class="grid min-w-0 gap-5">
            <header class="grid gap-2">
                <div class="flex flex-wrap justify-between gap-2">
                    <h1 class="break-words text-page-title">
                        {{ task.title }}
                    </h1>
                    <span
                        class="rounded-full border border-separator px-3 py-1"
                        >{{ task.status_label }}</span
                    >
                </div>
                <p>
                    {{ task.assignee }} · échéance {{ task.due_date }} ·
                    {{ task.priority_label }}
                </p>
                <p v-if="task.project">Projet : {{ task.project }}</p>
                <p v-if="task.objective">Objectif : {{ task.objective }}</p>
                <p v-if="task.parent">Sous-tâche de : {{ task.parent }}</p>
            </header>
            <section
                v-if="canUpdate && task.transitions.length"
                class="grid min-w-0 gap-3 rounded-xl border border-primary p-4"
                aria-labelledby="task-status-title"
            >
                <h2 id="task-status-title" class="text-section-title">
                    Faire avancer la tâche
                </h2>
                <label class="grid gap-1"
                    >Précision (facultative)<input
                        v-model="statusForm.reason"
                        maxlength="1000"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3"
                /></label>
                <div class="flex flex-wrap gap-2">
                    <AppButton
                        v-for="transition in task.transitions"
                        :key="transition.value"
                        type="button"
                        :variant="transition.value === 'terminee' ? 'principal' : 'secondaire'"
                        :busy="statusForm.processing && statusForm.status === transition.value"
                        @click="changeStatus(transition.value)"
                        >{{ transition.label }}</AppButton
                    >
                </div>
                <p v-if="statusForm.errors.status" class="text-danger">
                    {{ statusForm.errors.status }}
                </p>
            </section>
            <section class="grid gap-3">
                <h2 class="text-section-title">Documents et liens</h2>
                <ul v-if="task.attachments.length" class="grid gap-2"><li v-for="item in task.attachments" :key="item.url"><a :href="item.url" class="touch-target text-primary">{{ item.name }}</a></li></ul>
                <ul v-if="task.links.length" class="grid gap-2"><li v-for="item in task.links" :key="item.id"><a :href="item.url" rel="noopener noreferrer" class="touch-target text-primary">{{ item.label }}</a></li></ul>
            </section>
            <form v-if="canUpdate" class="grid min-w-0 gap-3 rounded-xl border border-separator p-4" @submit.prevent="send(file, `/taches/${task.id}/pieces-jointes`)">
                <AttachmentUploader attachable-type="person" :attachable-id="attachment.attachable_id" :allowed-types="attachment.allowed_types" :max-size-bytes="attachment.max_size_bytes" @uploaded="file.attachment_ulid = $event.ulid" />
                <p v-if="file.errors.attachment_ulid" class="text-danger">{{ file.errors.attachment_ulid }}</p>
                <AppButton type="submit" :busy="file.processing" :disabled="!file.attachment_ulid">Rattacher le document</AppButton>
            </form>
            <form v-if="canUpdate" class="grid min-w-0 gap-3 rounded-xl border border-separator p-4" @submit.prevent="send(link, `/taches/${task.id}/liens`)">
                <label class="grid gap-1">Libellé du lien<input v-model="link.label" required class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                <label class="grid gap-1">Adresse du lien<input v-model="link.url" required type="url" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                <ul v-if="link.hasErrors" class="grid gap-1 text-danger" role="alert"><li v-for="(message, field) in link.errors" :key="field">{{ message }}</li></ul>
                <AppButton type="submit" :busy="link.processing">Ajouter le lien</AppButton>
            </form>
            <section v-if="task.comments.length" class="grid gap-3"><h2 class="text-section-title">Commentaires</h2><ul class="grid gap-2"><li v-for="item in task.comments" :key="item.id" class="rounded-lg border border-separator p-3"><strong>{{ item.author }}</strong><p>{{ item.body }}</p></li></ul></section>
            <form
                v-if="canUpdate"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4"
                @submit.prevent="send(comment, `/taches/${task.id}/commentaires`)"
            >
                <label class="grid gap-1"
                    >Commentaire<textarea
                        v-model="comment.body"
                        required
                        class="min-w-0 rounded-lg border border-separator p-3"
                    /></label
                ><p v-if="comment.errors.body" class="text-danger" role="alert">{{ comment.errors.body }}</p><AppButton type="submit" :busy="comment.processing"
                    >Ajouter le commentaire</AppButton
                >
            </form>
        </article></AppLayout
    >
</template>
