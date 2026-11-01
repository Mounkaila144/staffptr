<script setup>
import { ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AppIcon from "../../../Components/AppIcon.vue";
import AppModal from "../../../Components/AppModal.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import DataTable from "../../../Components/DataTable.vue";
import EmptyState from "../../../Components/EmptyState.vue";
import PageHeader from "../../../Components/PageHeader.vue";
import StatusBadge from "../../../Components/StatusBadge.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({
    tasks: Object,
    filters: Object,
    filtersActive: Boolean,
    statuses: Array,
    priorities: Array,
    canCreate: Boolean,
    assignablePeople: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    objectives: { type: Array, default: () => [] },
    parentTasks: { type: Array, default: () => [] },
    attachment: Object,
});
const creating = ref(false);
const form = useForm({
    title: "",
    assignee_id: "",
    due_date: "",
    priority: "normale",
    status: "a_faire",
    project_id: "",
    objective_id: "",
    parent_id: "",
    link_label: "",
    link_url: "",
    attachment_ulid: "",
});
const filter = useForm({
    assignee_id: props.filters.assignee_id ?? "",
    due_date: props.filters.due_date ?? "",
    status: props.filters.status ?? "",
    project_id: props.filters.project_id ?? "",
});
const columns = [
    { key: "title", label: "Tâche", primary: true },
    { key: "assignee", label: "Responsable" },
    { key: "due_date", label: "Échéance" },
    { key: "priority_label", label: "Priorité" },
    { key: "status", label: "Statut" },
    { key: "project", label: "Projet", hideOnMobile: true },
];
// La priorité est dite en toutes lettres ; le point coloré n'en est que le rappel.
const priorityDots = { basse: "bg-separator-strong", normale: "bg-primary/50", haute: "bg-warning", urgente: "bg-danger" };

function applyFilters() {
    filter.get("/taches", { preserveState: true, replace: true });
}
function emptyAction() {
    if (props.filtersActive) {
        router.visit("/taches");
    } else {
        creating.value = true;
    }
}
function submit() {
    form.post("/taches", { onSuccess: () => { creating.value = false; form.reset(); } });
}
</script>
<template>
    <Head title="Tâches" /><AppLayout title="Tâches"
        ><div class="grid min-w-0 gap-6">
            <PageHeader title="Tâches" description="Qui fait quoi, pour quand : chaque tâche avance de « À faire » à « Terminée ».">
                <template #actions>
                    <Link href="/taches/aujourdhui" class="touch-target inline-flex items-center gap-2 rounded-lg border border-separator-strong bg-surface px-4 text-[0.9375rem] font-semibold text-primary shadow-sm transition-colors hover:border-primary hover:bg-primary-soft">
                        <AppIcon name="calendar" :size="18" />Mes tâches du jour
                    </Link>
                    <AppButton v-if="canCreate" variant="principal" @click="creating = true">
                        <span class="inline-flex items-center gap-2"><AppIcon name="tasks" :size="18" />Nouvelle tâche</span>
                    </AppButton>
                </template>
            </PageHeader>

            <form class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]" aria-label="Filtrer les tâches" @submit.prevent="applyFilters">
                <label class="grid gap-1 text-sm font-medium text-ink-secondary">Responsable<select v-model="filter.assignee_id" class="touch-target min-w-0 rounded-lg border border-separator px-3 text-base text-ink"><option value="">Tous</option><option v-for="person in assignablePeople" :key="person.id" :value="person.id">{{ person.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-medium text-ink-secondary">Échéance<input v-model="filter.due_date" type="date" class="touch-target min-w-0 rounded-lg border border-separator px-3 text-base text-ink"></label>
                <label class="grid gap-1 text-sm font-medium text-ink-secondary">Statut<select v-model="filter.status" class="touch-target min-w-0 rounded-lg border border-separator px-3 text-base text-ink"><option value="">Tous</option><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                <label class="grid gap-1 text-sm font-medium text-ink-secondary">Projet<select v-model="filter.project_id" class="touch-target min-w-0 rounded-lg border border-separator px-3 text-base text-ink"><option value="">Tous</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
                <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-1"><AppButton type="submit">Filtrer</AppButton><Link v-if="filtersActive" href="/taches" class="touch-target inline-flex items-center px-2 text-sm font-semibold text-primary">Réinitialiser les filtres</Link></div>
            </form>

            <DataTable v-if="tasks.data.length" :columns="columns" :rows="tasks.data" :row-href="(task) => `/taches/${task.id}`" caption="Liste des tâches">
                <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                <template #cell-priority_label="{ row }"><span class="inline-flex items-center gap-2"><span class="size-2 rounded-full" :class="priorityDots[row.priority]" aria-hidden="true" />{{ row.priority_label }}</span></template>
                <template #footer>
                    <nav class="flex flex-wrap items-center justify-between gap-2 text-sm text-ink-secondary" aria-label="Pagination des tâches">
                        <span>{{ tasks.total }} tâche{{ tasks.total > 1 ? 's' : '' }}<template v-if="tasks.last_page > 1"> · page {{ tasks.current_page }} sur {{ tasks.last_page }}</template></span>
                        <span v-if="tasks.last_page > 1" class="flex gap-2">
                            <Link v-if="tasks.prev_page_url" :href="tasks.prev_page_url" preserve-state class="touch-target inline-flex items-center rounded-lg border border-separator px-3 font-semibold text-primary">Précédente</Link>
                            <Link v-if="tasks.next_page_url" :href="tasks.next_page_url" preserve-state class="touch-target inline-flex items-center rounded-lg border border-separator px-3 font-semibold text-primary">Suivante</Link>
                        </span>
                    </nav>
                </template>
            </DataTable>
            <EmptyState
                v-else
                :tone="filtersActive ? 'filter' : 'neutral'"
                :title="
                    filtersActive
                        ? 'Aucune tâche ne correspond à ces filtres.'
                        : 'Aucune tâche planifiée.'
                "
                :reason="
                    filtersActive
                        ? 'Réinitialisez les filtres pour retrouver la liste complète.'
                        : 'Les tâches apparaîtront ici après leur création.'
                "
                :action-label="!filtersActive && canCreate ? 'Créer la première tâche' : ''"
                @action="emptyAction"
            />

            <AppModal v-if="canCreate" v-model:open="creating" size="lg" title="Nouvelle tâche" description="Le responsable la retrouve dans « Mes tâches du jour » à son échéance.">
                <form id="task-create-form" class="grid min-w-0 gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <label class="grid gap-1 font-medium sm:col-span-2">Titre<input v-model="form.title" required class="touch-target min-w-0 rounded-lg border border-separator px-3"><span v-if="form.errors.title" class="text-sm text-danger">{{ form.errors.title }}</span></label>
                    <label class="grid gap-1 font-medium">Responsable<select v-model="form.assignee_id" required class="touch-target min-w-0 rounded-lg border border-separator px-3"><option value="" disabled>Choisir une personne</option><option v-for="person in assignablePeople" :key="person.id" :value="person.id">{{ person.name }}</option></select><span v-if="form.errors.assignee_id" class="text-sm text-danger">{{ form.errors.assignee_id }}</span></label>
                    <label class="grid gap-1 font-medium">Échéance<input v-model="form.due_date" required type="date" class="touch-target min-w-0 rounded-lg border border-separator px-3"><span v-if="form.errors.due_date" class="text-sm text-danger">{{ form.errors.due_date }}</span></label>
                    <label class="grid gap-1 font-medium">Priorité<select v-model="form.priority" class="touch-target min-w-0 rounded-lg border border-separator px-3"><option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option></select></label>
                    <label class="grid gap-1 font-medium"><span>Projet <span class="font-normal text-ink-muted">(facultatif)</span></span><select v-model="form.project_id" class="touch-target min-w-0 rounded-lg border border-separator px-3"><option value="">Aucun projet</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
                    <label class="grid gap-1 font-medium"><span>Objectif <span class="font-normal text-ink-muted">(facultatif)</span></span><select v-model="form.objective_id" class="touch-target min-w-0 rounded-lg border border-separator px-3"><option value="">Aucun objectif</option><option v-for="objective in objectives" :key="objective.id" :value="objective.id">{{ objective.name }}</option></select></label>
                    <label class="grid gap-1 font-medium"><span>Tâche parente <span class="font-normal text-ink-muted">(un seul niveau)</span></span><select v-model="form.parent_id" class="touch-target min-w-0 rounded-lg border border-separator px-3"><option value="">Aucune tâche parente</option><option v-for="task in parentTasks" :key="task.id" :value="task.id">{{ task.name }}</option></select><span v-if="form.errors.parent_id" class="text-sm text-danger">{{ form.errors.parent_id }}</span></label>
                    <details class="grid gap-3 sm:col-span-2">
                        <summary class="touch-target flex cursor-pointer items-center font-semibold text-primary">Ajouter un lien ou une pièce jointe</summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-1 font-medium">Libellé du lien<input v-model="form.link_label" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                            <label class="grid gap-1 font-medium">Adresse du lien<input v-model="form.link_url" type="url" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                            <div class="min-w-0 sm:col-span-2"><AttachmentUploader attachable-type="person" :attachable-id="attachment.attachable_id" :allowed-types="attachment.allowed_types" :max-size-bytes="attachment.max_size_bytes" @uploaded="form.attachment_ulid = $event.ulid" /><p v-if="form.errors.attachment_ulid" class="text-sm text-danger">{{ form.errors.attachment_ulid }}</p></div>
                        </div>
                    </details>
                </form>
                <template #footer>
                    <AppButton @click="creating = false">Annuler</AppButton>
                    <AppButton type="submit" form="task-create-form" variant="principal" :busy="form.processing">Créer la tâche</AppButton>
                </template>
            </AppModal>
        </div></AppLayout
    >
</template>
