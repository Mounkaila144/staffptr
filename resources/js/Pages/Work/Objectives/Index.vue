<script setup>
import { computed } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import FormField from "../../../Components/FormField.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import EmptyState from "../../../Components/EmptyState.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
import { useDraft } from "../../../Composables/useDraft.js";
const props = defineProps({
    objectives: Object,
    month: String,
    filters: Object,
    filtersActive: Boolean,
    states: Array,
    priorities: Array,
    canCreate: Boolean,
    assignableOwners: { type: Array, default: () => [] },
    attachment: Object,
});
const ownerOptions = computed(() =>
    props.assignableOwners.map((o) => ({ value: o.id, label: o.name })),
);
const priorityOptions = computed(() =>
    (props.priorities ?? []).map((p) => ({ value: p.value, label: p.label })),
);
const form = useForm({
    user_id: usePage().props.auth?.user?.id ?? "",
    title: "",
    description: "",
    indicator: "",
    target_value: "",
    expected_evidence: "",
    required_means: "",
    due_date: "",
    priority: "normale",
    company_priority_id: "",
    project_id: "",
    progress: 0,
    attachment_ulid: "",
});
const draft = useDraft(
    "objective",
    usePage().props.auth?.user?.id,
    "new",
    form,
);
const restored = draft.restore();
if (restored) Object.assign(form, restored);
const submit = () =>
    form.post("/objectifs", { onSuccess: () => draft.purge() });
const emptyTitle = computed(() =>
    props.filtersActive
        ? "Aucun objectif ne correspond à ces filtres."
        : "Aucun objectif pour ce mois.",
);
</script>
<template>
    <Head title="Objectifs" /><AppLayout title="Objectifs"
        ><div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-3">
                <h1 class="text-page-title">Objectifs individuels</h1>
                <nav
                    class="flex flex-wrap gap-2"
                    aria-label="Vues des objectifs"
                >
                    <Link
                        href="/objectifs"
                        class="touch-target px-3 font-semibold text-primary"
                        >Liste</Link
                    ><Link
                        href="/objectifs/calendrier"
                        class="touch-target px-3 font-semibold text-primary"
                        >Calendrier</Link
                    ><Link
                        href="/objectifs/synthese"
                        class="touch-target px-3 font-semibold text-primary"
                        >Synthèse mensuelle</Link
                    >
                </nav>
            </header>
            <form
                v-if="canCreate"
                class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4"
                @submit.prevent="submit"
            >
                <h2 class="text-section-title">Proposer un objectif</h2>
                <p class="text-sm text-ink-secondary">
                    Les champs marqués
                    <span aria-hidden="true" class="text-danger">✱</span> sont
                    obligatoires. L’objectif part en brouillon : rien n’est
                    engagé tant qu’un responsable ne l’a pas validé.
                </p>
                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                    <FormField
                        id="objective-user-id"
                        v-model="form.user_id"
                        as="select"
                        label="Responsable"
                        hint="La personne qui s’engage sur cet objectif. Vous ne pouvez désigner que vous-même ou une personne de votre périmètre."
                        placeholder="Choisir un responsable"
                        :options="ownerOptions"
                        :error="form.errors.user_id"
                        required
                    />
                    <FormField
                        id="objective-title"
                        v-model="form.title"
                        label="Titre"
                        hint="Une phrase courte qui nomme le résultat visé, pas l’activité. 160 caractères au plus."
                        :maxlength="160"
                        :error="form.errors.title"
                        required
                    />
                    <div class="sm:col-span-2">
                        <FormField
                            id="objective-description"
                            v-model="form.description"
                            as="textarea"
                            label="Description courte"
                            hint="Ce que recouvre l’objectif et pourquoi il compte ce mois-ci. 500 caractères au plus."
                            :maxlength="500"
                            :error="form.errors.description"
                            required
                        />
                    </div>
                    <FormField
                        id="objective-indicator"
                        v-model="form.indicator"
                        label="Indicateur"
                        hint="Ce que l’on mesurera pour trancher. Exemple : « nombre de dossiers traités »."
                        :maxlength="160"
                        :error="form.errors.indicator"
                        required
                    />
                    <FormField
                        id="objective-target-value"
                        v-model="form.target_value"
                        label="Valeur cible"
                        hint="Le seuil à atteindre sur cet indicateur. Exemple : « 30 » ou « 100 % »."
                        :maxlength="160"
                        :error="form.errors.target_value"
                        required
                    />
                    <div class="sm:col-span-2">
                        <FormField
                            id="objective-expected-evidence"
                            v-model="form.expected_evidence"
                            as="textarea"
                            label="Preuve attendue"
                            hint="Le document ou la trace qui permettra de dire que l’objectif est atteint. Sans preuve, un objectif ne peut pas être déclaré atteint."
                            :maxlength="500"
                            :error="form.errors.expected_evidence"
                            required
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <FormField
                            id="objective-required-means"
                            v-model="form.required_means"
                            as="textarea"
                            label="Moyens nécessaires"
                            hint="Budget, matériel, appui d’un collègue… Ce qui manque aujourd’hui pour y arriver. À renseigner si vous avez besoin de quelque chose."
                            :maxlength="2000"
                            :rows="3"
                            :error="form.errors.required_means"
                            optional-label
                        />
                    </div>
                    <FormField
                        id="objective-due-date"
                        v-model="form.due_date"
                        variant="date"
                        label="Date limite"
                        hint="Le jour où l’objectif sera évalué. Il compte dans le mois de cette date."
                        :error="form.errors.due_date"
                        required
                    />
                    <FormField
                        id="objective-priority"
                        v-model="form.priority"
                        as="select"
                        label="Priorité"
                        hint="Sert au classement de vos objectifs. Ne change ni l’échéance ni la validation."
                        :options="priorityOptions"
                        :error="form.errors.priority"
                        required
                    />
                </div>
                <p class="text-sm text-ink-secondary">
                    Trois objectifs majeurs validés au maximum, par personne et
                    par mois. Le quatrième sera refusé.
                </p>
                <AttachmentUploader
                    attachable-type="person"
                    :attachable-id="attachment.attachable_id"
                    :allowed-types="attachment.allowed_types"
                    :max-size-bytes="attachment.max_size_bytes"
                    @uploaded="form.attachment_ulid = $event.ulid"
                />
                <p v-if="form.errors.attachment_ulid" class="text-danger">
                    {{ form.errors.attachment_ulid }}
                </p>
                <p class="text-sm text-ink-secondary" aria-live="polite">
                    {{ draft.savedLabel.value }}
                </p>
                <AppButton
                    type="submit"
                    variant="principal"
                    :busy="form.processing"
                    >Proposer en brouillon</AppButton
                >
            </form>
            <ul v-if="objectives.data.length" class="grid min-w-0 gap-3">
                <li
                    v-for="objective in objectives.data"
                    :key="objective.id"
                    class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4"
                >
                    <div class="flex min-w-0 flex-wrap justify-between gap-2">
                        <Link
                            :href="`/objectifs/${objective.id}`"
                            class="break-words font-semibold text-primary"
                            >{{ objective.title }}</Link
                        ><span
                            class="rounded-full border border-separator px-3 py-1"
                            >● {{ objective.state_label }} —
                            {{ objective.tone }}</span
                        >
                    </div>
                    <p class="text-sm text-ink-secondary">
                        {{ objective.owner }} · {{ objective.due_date }}
                    </p>
                    <div
                        class="h-3 overflow-hidden rounded-full bg-neutral-soft"
                        :aria-label="`Progression ${objective.progress} %`"
                    >
                        <span
                            class="block h-full bg-primary"
                            :style="{ width: `${objective.progress}%` }"
                        />
                    </div>
                    <p>{{ objective.progress }} %</p>
                </li>
            </ul>
            <EmptyState
                v-else
                :title="emptyTitle"
                :reason="
                    filtersActive
                        ? 'Réinitialisez les filtres pour retrouver tous les objectifs.'
                        : 'Proposez un objectif pour rendre le travail attendu explicite.'
                "
                :action-label="
                    filtersActive ? 'Réinitialiser les filtres' : undefined
                "
            /></div
    ></AppLayout>
</template>
