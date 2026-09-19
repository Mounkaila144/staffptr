<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({
    objective: Object,
    canUpdate: Boolean,
    canValidate: Boolean,
    attachment: Object,
});
const transition = useForm({ state: "", attachment_ulid: "" });
const comment = useForm({ body: "", correction_requested: false });
</script>
<template>
    <Head :title="objective.title" /><AppLayout
        title="Objectif"
        back-label="Retour aux objectifs"
        back-href="/objectifs"
        ><article class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <div class="flex flex-wrap justify-between gap-2">
                    <h1 class="break-words text-page-title">
                        {{ objective.title }}
                    </h1>
                    <span class="rounded-full border border-separator px-3 py-1"
                        >● {{ objective.state_label }} —
                        {{ objective.tone }}</span
                    >
                </div>
                <p>{{ objective.description }}</p>
                <p>
                    <strong>Preuve attendue :</strong>
                    {{ objective.expected_evidence }}
                </p>
                <p>
                    Progression : {{ objective.progress }} % · version
                    {{ objective.version_number }}
                </p>
            </header>
            <section v-if="objective.attachments.length" class="grid gap-2">
                <h2 class="text-section-title">Preuves jointes</h2>
                <a
                    v-for="file in objective.attachments"
                    :key="file.url"
                    :href="file.url"
                    class="touch-target w-fit text-primary"
                    >{{ file.name }}</a
                >
            </section>
            <form
                v-if="canValidate && objective.state === 'brouillon'"
                method="post"
                @submit.prevent="
                    $inertia.patch(`/objectifs/${objective.id}/valider`)
                "
            >
                <AppButton type="submit" variant="principal"
                    >Valider l’objectif</AppButton
                >
            </form>
            <form
                v-if="canUpdate"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4"
                @submit.prevent="
                    transition.patch(`/objectifs/${objective.id}/etat`)
                "
            >
                <h2 class="text-section-title">Mettre à jour l’état</h2>
                <select
                    v-model="transition.state"
                    required
                    class="touch-target min-w-0 rounded-lg border border-separator px-3"
                >
                    <option value="en_cours">En cours</option>
                    <option value="bloque">Bloqué</option>
                    <option value="atteint">Atteint</option>
                    <option value="partiellement_atteint">
                        Partiellement atteint
                    </option>
                    <option value="non_atteint">Non atteint</option>
                    <option value="annule">Annulé</option></select
                ><AttachmentUploader
                    attachable-type="person"
                    :attachable-id="attachment.attachable_id"
                    :allowed-types="attachment.allowed_types"
                    :max-size-bytes="attachment.max_size_bytes"
                    @uploaded="transition.attachment_ulid = $event.ulid"
                />
                <p v-if="transition.errors.attachment_ulid" class="text-danger">
                    {{ transition.errors.attachment_ulid }}
                </p>
                <AppButton
                    type="submit"
                    variant="principal"
                    :busy="transition.processing"
                    >Mettre à jour</AppButton
                >
            </form>
            <form
                v-if="canUpdate"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4"
                @submit.prevent="
                    comment.post(`/objectifs/${objective.id}/commentaires`)
                "
            >
                <h2 class="text-section-title">Commenter</h2>
                <textarea
                    v-model="comment.body"
                    required
                    class="min-w-0 rounded-lg border border-separator p-3"
                /><label class="touch-target flex items-center gap-2"
                    ><input
                        v-model="comment.correction_requested"
                        type="checkbox"
                    />Demander une correction</label
                ><AppButton type="submit" :busy="comment.processing"
                    >Ajouter le commentaire</AppButton
                >
            </form>
        </article></AppLayout
    >
</template>
