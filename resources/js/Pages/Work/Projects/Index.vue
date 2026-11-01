<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AppModal from "../../../Components/AppModal.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import DataTable from "../../../Components/DataTable.vue";
import EmptyState from "../../../Components/EmptyState.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({
    projects: Object,
    filters: Object,
    filtersActive: Boolean,
    statuses: Array,
    canCreate: Boolean,
    assignablePeople: { type: Array, default: () => [] },
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
const creating = ref(false);
const projectColumns = [
    { key: "name", label: "Projet", primary: true },
    { key: "client_name", label: "Client" },
    { key: "manager", label: "Responsable" },
    { key: "start_date", label: "Période" },
    { key: "status_label", label: "Statut" },
];
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
                <AppButton v-if="canCreate" variant="principal" class="justify-self-start" @click="creating = true">Nouveau projet</AppButton>
            </header>
            <AppModal v-if="canCreate" v-model:open="creating" size="lg" title="Créer un projet">
                <form class="grid min-w-0 gap-4 sm:grid-cols-2" @submit.prevent="form.post('/projets', { onSuccess: () => { creating = false } })">
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
                    >Responsable<select
                        v-model="form.manager_id"
                        required
                        class="touch-target min-w-0 rounded-lg border border-separator bg-surface px-3"
                    >
                        <option value="" disabled>Choisir une personne</option>
                        <option
                            v-for="person in assignablePeople"
                            :key="person.id"
                            :value="person.id"
                        >
                            {{ person.name }}
                        </option>
                    </select></label
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
                </div><ul v-if="form.hasErrors" class="grid gap-1 text-danger sm:col-span-2" role="alert"><li v-for="(message, field) in form.errors" :key="field">{{ message }}</li></ul><AppButton
                    type="submit"
                    variant="principal"
                    :busy="form.processing"
                    >Créer le projet</AppButton
                >
            </form>
            </AppModal>
            <DataTable v-if="projects.data.length" :columns="projectColumns" :rows="projects.data" :row-href="(project) => `/projets/${project.id}`" caption="Liste des projets">
                <template #cell-status_label="{ row }"><span class="inline-flex rounded-full border border-separator-strong px-2.5 py-0.5 text-[0.8125rem] font-semibold text-ink-secondary">{{ row.status_label }}</span></template>
                <template #cell-start_date="{ row }">{{ row.start_date }}<template v-if="row.end_date"> → {{ row.end_date }}</template></template>
            </DataTable>
            <EmptyState
                v-else
                :title="emptyMessage"
                reason="Les projets apparaîtront ici après leur création."
                :action-label="canCreate ? 'Créer un projet' : undefined"
                @action="creating = true"
            /></div
    ></AppLayout>
</template>
