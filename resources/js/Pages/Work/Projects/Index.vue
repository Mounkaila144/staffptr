<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import EmptyState from "../../../Components/EmptyState.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({
    projects: Object,
    filters: Object,
    filtersActive: Boolean,
    statuses: Array,
    canCreate: Boolean,
    emptyMessage: String,
    attachment: Object,
});
const form = useForm({
    name: "",
    client_name: "",
    manager_id: "",
    start_date: "",
    end_date: "",
    status: "prevu",
    planned_budget_xof: "",
    spent_budget_xof: "",
    attachment_ulid: "",
});
</script>
<template>
    <Head title="Projets" /><AppLayout title="Projets"
        ><div class="grid min-w-0 gap-6">
            <header class="grid gap-2">
                <h1 class="text-page-title">Projets</h1>
                <p class="text-ink-secondary">
                    Responsabilités, membres, dates et livrables réunis au même
                    endroit.
                </p>
            </header>
            <form
                v-if="canCreate"
                class="grid min-w-0 gap-4 rounded-xl border border-separator p-4 sm:grid-cols-2"
                @submit.prevent="form.post('/projets')"
            >
                <h2 class="text-section-title sm:col-span-2">
                    Créer un projet
                </h2>
                <label class="grid gap-1"
                    >Nom<input
                        v-model="form.name"
                        required
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Client optionnel<input
                        v-model="form.client_name"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Responsable (identifiant)<input
                        v-model="form.manager_id"
                        required
                        inputmode="numeric"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Statut<select
                        v-model="form.status"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3"
                    >
                        <option
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </option>
                    </select></label
                ><label class="grid gap-1"
                    >Début<input
                        v-model="form.start_date"
                        required
                        type="date"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Fin prévue<input
                        v-model="form.end_date"
                        type="date"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Budget prévu (XOF)<input
                        v-model="form.planned_budget_xof"
                        inputmode="numeric"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Budget dépensé (XOF)<input
                        v-model="form.spent_budget_xof"
                        inputmode="numeric"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><div class="min-w-0 sm:col-span-2">
                    <AttachmentUploader
                        attachable-type="person"
                        :attachable-id="attachment.attachable_id"
                        :allowed-types="attachment.allowed_types"
                        :max-size-bytes="attachment.max_size_bytes"
                        @uploaded="form.attachment_ulid = $event.ulid"
                    />
                    <p v-if="form.errors.attachment_ulid" class="text-danger">{{ form.errors.attachment_ulid }}</p>
                </div><AppButton
                    type="submit"
                    variant="principal"
                    :busy="form.processing"
                    >Créer le projet</AppButton
                >
            </form>
            <ul v-if="projects.data.length" class="grid min-w-0 gap-3">
                <li
                    v-for="project in projects.data"
                    :key="project.id"
                    class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <Link
                            :href="`/projets/${project.id}`"
                            class="break-words font-semibold text-primary"
                            >{{ project.name }}</Link
                        ><span
                            class="rounded-full border border-separator px-3 py-1"
                            >{{ project.status_label }}</span
                        >
                    </div>
                    <p>
                        {{ project.manager }} · {{ project.start_date
                        }}<template v-if="project.end_date">
                            au {{ project.end_date }}</template
                        >
                    </p>
                    <p
                        v-if="project.client_name"
                        class="text-sm text-ink-secondary"
                    >
                        Client : {{ project.client_name }}
                    </p>
                </li>
            </ul>
            <EmptyState
                v-else
                :title="emptyMessage"
                reason="Les projets apparaîtront ici après leur création."
                :action-label="canCreate ? 'Créer un projet' : undefined"
            /></div
    ></AppLayout>
</template>
