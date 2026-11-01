<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import FormField from "../../../Components/FormField.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({
    objective: Object,
    canUpdate: Boolean,
    canValidate: Boolean,
    canCopy: Boolean,
    priorities: { type: Array, default: () => [] },
    attachment: Object,
});
const page = usePage();
// Les refus de validation (limite des trois objectifs, état) reviennent sur la page,
// pas sur un formulaire : ils doivent rester lisibles.
const pageErrors = computed(() => page.props.errors ?? {});
const validating = ref(false);
const validateObjective = () =>
    router.patch(
        `/objectifs/${props.objective.id}/valider`,
        {},
        {
            preserveScroll: true,
            onStart: () => (validating.value = true),
            onFinish: () => (validating.value = false),
        },
    );
const copying = ref(false);
const copyObjective = () =>
    router.post(
        `/objectifs/${props.objective.id}/copier`,
        {},
        {
            onStart: () => (copying.value = true),
            onFinish: () => (copying.value = false),
        },
    );

const transition = useForm({ state: "", attachment_ulid: "" });
const submitTransition = () =>
    transition.patch(`/objectifs/${props.objective.id}/etat`, {
        preserveScroll: true,
        onSuccess: () => transition.reset(),
    });

const comment = useForm({ body: "", correction_requested: false });
const submitComment = () =>
    comment.post(`/objectifs/${props.objective.id}/commentaires`, {
        preserveScroll: true,
        onSuccess: () => comment.reset(),
    });

const priorityOptions = computed(() =>
    props.priorities.map((p) => ({ value: p.value, label: p.label })),
);
const isDraft = computed(() => props.objective.state === "brouillon");
// « progress » est une propriété réservée de useForm : le champ porte un autre nom
// côté client et repart sous « progress » vers le serveur.
const editFields = () => ({
    user_id: props.objective.user_id,
    company_priority_id: props.objective.company_priority_id ?? "",
    project_id: props.objective.project_id ?? "",
    title: props.objective.title,
    description: props.objective.description,
    indicator: props.objective.indicator,
    target_value: props.objective.target_value,
    expected_evidence: props.objective.expected_evidence,
    required_means: props.objective.required_means ?? "",
    due_date: props.objective.due_date,
    priority: props.objective.priority,
    progress_value: props.objective.progress,
    reason: "",
});
const edit = useForm(editFields());
const editing = ref(false);
const startEditing = () => {
    edit.defaults(editFields());
    edit.reset();
    edit.clearErrors();
    editing.value = true;
};
const submitEdit = () =>
    edit
        .transform(({ progress_value, ...data }) => ({
            ...data,
            progress: progress_value === "" ? null : Number(progress_value),
        }))
        .patch(`/objectifs/${props.objective.id}`, {
            preserveScroll: true,
            onSuccess: () => (editing.value = false),
        });
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
                        >{{ objective.state_label }}</span
                    >
                </div>
                <p class="text-sm text-ink-secondary">
                    {{ objective.owner }} · échéance
                    {{ objective.due_date_label }} ·
                    {{ objective.priority_label }}
                </p>
                <p class="break-words">{{ objective.description }}</p>
                <p>
                    <strong>{{ objective.indicator }} :</strong>
                    {{ objective.target_value }}
                </p>
                <p class="break-words">
                    <strong>Preuve attendue :</strong>
                    {{ objective.expected_evidence }}
                </p>
                <p v-if="objective.required_means" class="break-words">
                    <strong>Moyens nécessaires :</strong>
                    {{ objective.required_means }}
                </p>
                <p>
                    Progression : {{ objective.progress }} % · version
                    {{ objective.version_number }}
                </p>
            </header>
            <p
                v-if="pageErrors.state && !transition.errors.state"
                class="rounded-lg border border-danger p-3 text-danger"
                role="alert"
            >
                {{ pageErrors.state }}
            </p>
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
            <div class="flex flex-wrap gap-2">
                <AppButton
                    v-if="canValidate && isDraft"
                    type="button"
                    variant="principal"
                    :busy="validating"
                    @click="validateObjective"
                    >Valider l’objectif</AppButton
                >
                <AppButton
                    v-if="canUpdate && !editing"
                    type="button"
                    @click="startEditing"
                    >Modifier l’objectif</AppButton
                >
                <AppButton
                    v-if="canCopy"
                    type="button"
                    :busy="copying"
                    @click="copyObjective"
                    >Copier vers le mois suivant</AppButton
                >
            </div>
            <form
                v-if="canUpdate && editing"
                class="grid min-w-0 gap-4 rounded-xl border border-primary p-4"
                @submit.prevent="submitEdit"
            >
                <h2 class="text-section-title">Modifier l’objectif</h2>
                <p v-if="!isDraft" class="text-sm text-ink-secondary">
                    L’objectif est déjà validé : chaque modification crée une
                    nouvelle version et doit être justifiée par un motif.
                </p>
                <p
                    v-if="edit.errors.user_id"
                    class="text-danger"
                    role="alert"
                >
                    {{ edit.errors.user_id }}
                </p>
                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <FormField
                            id="edit-title"
                            v-model="edit.title"
                            label="Titre"
                            :maxlength="160"
                            :error="edit.errors.title"
                            required
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <FormField
                            id="edit-description"
                            v-model="edit.description"
                            as="textarea"
                            label="Description courte"
                            :maxlength="500"
                            :rows="3"
                            :error="edit.errors.description"
                            required
                        />
                    </div>
                    <FormField
                        id="edit-indicator"
                        v-model="edit.indicator"
                        label="Indicateur"
                        :maxlength="160"
                        :error="edit.errors.indicator"
                        required
                    />
                    <FormField
                        id="edit-target-value"
                        v-model="edit.target_value"
                        label="Valeur cible"
                        :maxlength="160"
                        :error="edit.errors.target_value"
                        required
                    />
                    <div class="sm:col-span-2">
                        <FormField
                            id="edit-expected-evidence"
                            v-model="edit.expected_evidence"
                            as="textarea"
                            label="Preuve attendue"
                            :maxlength="500"
                            :rows="2"
                            :error="edit.errors.expected_evidence"
                            required
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <FormField
                            id="edit-required-means"
                            v-model="edit.required_means"
                            as="textarea"
                            label="Moyens nécessaires"
                            :maxlength="2000"
                            :rows="2"
                            :error="edit.errors.required_means"
                            optional-label
                        />
                    </div>
                    <FormField
                        id="edit-due-date"
                        v-model="edit.due_date"
                        variant="date"
                        label="Date limite"
                        :error="edit.errors.due_date"
                        required
                    />
                    <FormField
                        id="edit-priority"
                        v-model="edit.priority"
                        as="select"
                        label="Priorité"
                        :options="priorityOptions"
                        :error="edit.errors.priority"
                        required
                    />
                    <FormField
                        id="edit-progress"
                        v-model="edit.progress_value"
                        variant="number"
                        label="Progression (%)"
                        hint="Un nombre entre 0 et 100."
                        :error="edit.errors.progress"
                        optional-label
                    />
                </div>
                <FormField
                    id="edit-reason"
                    v-model="edit.reason"
                    as="textarea"
                    label="Motif de la modification"
                    :hint="
                        isDraft
                            ? 'Facultatif tant que l’objectif est en brouillon.'
                            : 'Obligatoire : 5 caractères au moins.'
                    "
                    :maxlength="1000"
                    :rows="2"
                    :error="edit.errors.reason"
                    :required="!isDraft"
                    :optional-label="isDraft"
                />
                <div class="flex flex-wrap gap-2">
                    <AppButton
                        type="submit"
                        variant="principal"
                        :busy="edit.processing"
                        >Enregistrer la modification</AppButton
                    >
                    <AppButton type="button" @click="editing = false"
                        >Abandonner</AppButton
                    >
                </div>
            </form>
            <form
                v-if="canUpdate && objective.transitions.length"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4"
                @submit.prevent="submitTransition"
            >
                <h2 class="text-section-title">Mettre à jour l’état</h2>
                <FormField
                    id="objective-transition-state"
                    v-model="transition.state"
                    as="select"
                    label="Nouvel état"
                    hint="Seuls les passages possibles depuis l’état actuel sont proposés. « Atteint » exige une preuve jointe."
                    placeholder="Choisir un état"
                    :options="objective.transitions"
                    :error="transition.errors.state"
                    required
                />
                <AttachmentUploader
                    attachable-type="person"
                    :attachable-id="attachment.attachable_id"
                    :allowed-types="attachment.allowed_types"
                    :max-size-bytes="attachment.max_size_bytes"
                    @uploaded="transition.attachment_ulid = $event.ulid"
                />
                <p
                    v-if="transition.errors.attachment_ulid"
                    class="text-danger"
                    role="alert"
                >
                    {{ transition.errors.attachment_ulid }}
                </p>
                <AppButton
                    type="submit"
                    variant="principal"
                    :busy="transition.processing"
                    >Mettre à jour</AppButton
                >
            </form>
            <section v-if="objective.versions.length" class="grid gap-3">
                <h2 class="text-section-title">Historique des modifications</h2>
                <ul class="grid gap-2">
                    <li
                        v-for="version in objective.versions"
                        :key="version.id"
                        class="rounded-lg border border-separator p-3"
                    >
                        <strong>Version {{ version.version_number }}</strong> ·
                        {{ version.author }} · {{ version.created_at }}
                        <p class="break-words">Motif : {{ version.reason }}</p>
                    </li>
                </ul>
            </section>
            <section v-if="objective.comments.length" class="grid gap-3">
                <h2 class="text-section-title">Commentaires</h2>
                <ul class="grid gap-2">
                    <li
                        v-for="item in objective.comments"
                        :key="item.id"
                        class="rounded-lg border border-separator p-3"
                    >
                        <strong>{{ item.author }}</strong> ·
                        <span class="text-sm text-ink-secondary">{{
                            item.created_at
                        }}</span>
                        <p
                            v-if="item.correction_requested"
                            class="font-semibold"
                        >
                            Correction demandée
                        </p>
                        <p class="break-words">{{ item.body }}</p>
                    </li>
                </ul>
            </section>
            <form
                v-if="canUpdate"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4"
                @submit.prevent="submitComment"
            >
                <h2 class="text-section-title">Commenter</h2>
                <FormField
                    id="objective-comment"
                    v-model="comment.body"
                    as="textarea"
                    label="Commentaire"
                    :maxlength="2000"
                    :rows="3"
                    :error="comment.errors.body"
                    required
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
