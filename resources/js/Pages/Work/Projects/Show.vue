<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
defineProps({ project: Object, canManage: Boolean, assignablePeople: { type: Array, default: () => [] }, attachment: Object });
const status = useForm({ status: "", reason: "" });
const member = useForm({ action: "add", user_id: "", date: "" });
const comment = useForm({ body: "" });
const link = useForm({ label: "", url: "" });
const file = useForm({ attachment_ulid: "" });
// Chaque formulaire se vide après un enregistrement réussi.
const send = (form, method, url) => form[method](url, { preserveScroll: true, onSuccess: () => form.reset() });
</script>
<template>
    <Head :title="project.name" /><AppLayout
        title="Projet"
        back-label="Retour aux projets"
        back-href="/projets"
        ><article class="grid min-w-0 gap-6">
            <header class="grid gap-2">
                <div class="flex flex-wrap justify-between gap-2">
                    <h1 class="text-page-title">{{ project.name }}</h1>
                    <span
                        class="rounded-full border border-separator px-3 py-1"
                        >{{ project.status_label }}</span
                    >
                </div>
                <p>Responsable : {{ project.manager }}</p>
                <p v-if="project.client_name">
                    Client : {{ project.client_name }}
                </p>
                <Link
                    v-if="project.budget"
                    :href="project.budget.url"
                    class="touch-target w-fit font-semibold text-primary"
                    >Consulter le budget</Link
                >
            </header>
            <section class="grid gap-3">
                <h2 class="text-section-title">
                    Membres et participation passée
                </h2>
                <ul class="grid gap-2">
                    <li
                        v-for="item in project.members"
                        :key="`${item.name}-${item.joined_on}`"
                        class="rounded-lg border border-separator p-3"
                    >
                        {{ item.name }} · depuis {{ item.joined_on
                        }}<span v-if="item.left_on">
                            · retiré le {{ item.left_on }}</span
                        >
                    </li>
                </ul>
            </section>
            <section class="grid gap-3">
                <h2 class="text-section-title">Documents, liens et commentaires</h2>
                <ul v-if="project.attachments.length" class="grid gap-2">
                    <li v-for="item in project.attachments" :key="item.url"><a :href="item.url" class="touch-target text-primary">{{ item.name }}</a></li>
                </ul>
                <ul v-if="project.links.length" class="grid gap-2">
                    <li v-for="item in project.links" :key="item.id"><a :href="item.url" class="touch-target text-primary" rel="noopener noreferrer">{{ item.label }}</a></li>
                </ul>
                <ul v-if="project.comments.length" class="grid gap-2">
                    <li v-for="item in project.comments" :key="item.id" class="rounded-lg border border-separator p-3"><strong>{{ item.author }}</strong><p>{{ item.body }}</p></li>
                </ul>
            </section>
            <div v-if="canManage" class="grid min-w-0 gap-4 rounded-xl border border-separator p-4">
                <form class="grid gap-3" @submit.prevent="send(file, 'post', `/projets/${project.id}/pieces-jointes`)">
                    <AttachmentUploader attachable-type="person" :attachable-id="attachment.attachable_id" :allowed-types="attachment.allowed_types" :max-size-bytes="attachment.max_size_bytes" @uploaded="file.attachment_ulid = $event.ulid" />
                    <p v-if="file.errors.attachment_ulid" class="text-danger">{{ file.errors.attachment_ulid }}</p>
                    <AppButton type="submit" :busy="file.processing" :disabled="!file.attachment_ulid">Rattacher le document</AppButton>
                </form>
                <form class="grid gap-3" @submit.prevent="send(link, 'post', `/projets/${project.id}/liens`)">
                    <label class="grid gap-1">Libellé du lien<input v-model="link.label" required class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                    <label class="grid gap-1">Adresse du lien<input v-model="link.url" required type="url" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                    <ul v-if="link.hasErrors" class="grid gap-1 text-danger" role="alert"><li v-for="(message, field) in link.errors" :key="field">{{ message }}</li></ul>
                    <AppButton type="submit" :busy="link.processing">Ajouter le lien</AppButton>
                </form>
                <form class="grid gap-3" @submit.prevent="send(comment, 'post', `/projets/${project.id}/commentaires`)">
                    <label class="grid gap-1">Commentaire<textarea v-model="comment.body" required class="min-w-0 rounded-lg border border-separator p-3" /></label>
                    <p v-if="comment.errors.body" class="text-danger" role="alert">{{ comment.errors.body }}</p>
                    <AppButton type="submit" :busy="comment.processing">Ajouter le commentaire</AppButton>
                </form>
            </div>
            <form
                v-if="canManage && project.transitions.length"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4"
                @submit.prevent="send(status, 'patch', `/projets/${project.id}/etat`)"
            >
                <h2 class="text-section-title">Changer le statut</h2>
                <label class="grid gap-1"
                    >Nouveau statut<select
                        v-model="status.status"
                        required
                        class="touch-target min-w-0 rounded-lg border border-separator px-3"
                    >
                        <option value="" disabled>Choisir un statut</option>
                        <option v-for="item in project.transitions" :key="item.value" :value="item.value">{{ item.label }}</option></select
                ></label><label class="grid gap-1"
                    >Motif du changement<textarea
                        v-model="status.reason"
                        required
                        minlength="5"
                        class="min-w-0 rounded-lg border border-separator p-3"
                /></label><ul v-if="status.hasErrors" class="grid gap-1 text-danger" role="alert"><li v-for="(message, field) in status.errors" :key="field">{{ message }}</li></ul><AppButton
                    type="submit"
                    variant="principal"
                    :busy="status.processing"
                    >Enregistrer le statut</AppButton
                >
            </form>
            <form
                v-if="canManage"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4"
                @submit.prevent="send(member, 'patch', `/projets/${project.id}/membres`)"
            >
                <h2 class="text-section-title">Mettre à jour un membre</h2>
                <label class="grid gap-1">Action<select
                    v-model="member.action"
                    class="touch-target min-w-0 rounded-lg border border-separator px-3"
                >
                    <option value="add">Ajouter</option>
                    <option value="remove">
                        Retirer en conservant l’historique
                    </option></select
                ></label><label class="grid gap-1"
                    >Membre<select
                        v-model="member.user_id"
                        required
                        class="touch-target min-w-0 rounded-lg border border-separator bg-surface px-3"
                    >
                        <option value="" disabled>Choisir une personne</option>
                        <option v-for="person in assignablePeople" :key="person.id" :value="person.id">
                            {{ person.name }}
                        </option>
                    </select></label
                ><label class="grid gap-1"
                    >Date d’arrivée ou de départ<input
                        v-model="member.date"
                        required
                        type="date"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3"
                /></label><ul v-if="member.hasErrors" class="grid gap-1 text-danger" role="alert"><li v-for="(message, field) in member.errors" :key="field">{{ message }}</li></ul><AppButton type="submit" :busy="member.processing"
                    >Mettre à jour</AppButton
                >
            </form>
            <section class="grid gap-3">
                <h2 class="text-section-title">Livrables</h2>
                <ul class="grid gap-2">
                    <li
                        v-for="item in project.deliverables"
                        :key="item.id"
                        class="rounded-lg border border-separator p-3"
                    >
                        <strong>{{ item.title }}</strong>
                        <p>
                            {{ item.status_label }} · {{ item.variance_label }}
                        </p>
                    </li>
                </ul>
            </section>
        </article></AppLayout
    >
</template>
