<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import AttachmentUploader from "../../../Components/AttachmentUploader.vue";
import EmptyState from "../../../Components/EmptyState.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({
    tasks: Object,
    filters: Object,
    filtersActive: Boolean,
    statuses: Array,
    priorities: Array,
    canCreate: Boolean,
    attachment: Object,
});
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
</script>
<template>
    <Head title="Tâches" /><AppLayout title="Tâches"
        ><div class="grid min-w-0 gap-6">
            <header class="grid gap-2">
                <h1 class="text-page-title">Tâches</h1>
                <Link
                    href="/taches/aujourdhui"
                    class="touch-target w-fit px-3 font-semibold text-primary"
                    >Voir mes tâches du jour</Link
                >
            </header>
            <form class="grid min-w-0 gap-3 rounded-xl border border-separator p-4 sm:grid-cols-4" @submit.prevent="filter.get('/taches', { preserveState: true, replace: true })">
                <h2 class="text-section-title sm:col-span-4">Filtrer les tâches</h2>
                <label class="grid gap-1">Responsable<input v-model="filter.assignee_id" inputmode="numeric" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                <label class="grid gap-1">Échéance<input v-model="filter.due_date" type="date" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                <label class="grid gap-1">Statut<select v-model="filter.status" class="touch-target min-w-0 rounded-lg border border-separator px-3"><option value="">Tous</option><option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                <label class="grid gap-1">Projet<input v-model="filter.project_id" inputmode="numeric" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                <div class="flex flex-wrap gap-2 sm:col-span-4"><AppButton type="submit">Appliquer</AppButton><Link v-if="filtersActive" href="/taches" class="touch-target px-3 font-semibold text-primary">Réinitialiser les filtres</Link></div>
            </form>
            <form
                v-if="canCreate"
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4 sm:grid-cols-2"
                @submit.prevent="form.post('/taches')"
            >
                <h2 class="text-section-title sm:col-span-2">
                    Créer une tâche ou sous-tâche
                </h2>
                <label class="grid gap-1"
                    >Titre<input
                        v-model="form.title"
                        required
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Responsable (identifiant)<input
                        v-model="form.assignee_id"
                        required
                        inputmode="numeric"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Échéance<input
                        v-model="form.due_date"
                        required
                        type="date"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Priorité<select
                        v-model="form.priority"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3"
                    >
                        <option
                            v-for="p in priorities"
                            :key="p.value"
                            :value="p.value"
                        >
                            {{ p.label }}
                        </option>
                    </select></label
                ><label class="grid gap-1"
                    >Projet optionnel<input
                        v-model="form.project_id"
                        inputmode="numeric"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1"
                    >Objectif optionnel<input
                        v-model="form.objective_id"
                        inputmode="numeric"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3" /></label
                ><label class="grid gap-1 sm:col-span-2"
                    >Tâche parente optionnelle — un seul niveau<input
                        v-model="form.parent_id"
                        inputmode="numeric"
                        class="touch-target min-w-0 rounded-lg border border-separator px-3"
                    /><span v-if="form.errors.parent_id" class="text-danger">{{
                        form.errors.parent_id
                    }}</span></label
                ><label class="grid gap-1">Libellé du lien optionnel<input v-model="form.link_label" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                <label class="grid gap-1">Adresse du lien optionnel<input v-model="form.link_url" type="url" class="touch-target min-w-0 rounded-lg border border-separator px-3"></label>
                <div class="min-w-0 sm:col-span-2"><AttachmentUploader attachable-type="person" :attachable-id="attachment.attachable_id" :allowed-types="attachment.allowed_types" :max-size-bytes="attachment.max_size_bytes" @uploaded="form.attachment_ulid = $event.ulid" /><p v-if="form.errors.attachment_ulid" class="text-danger">{{ form.errors.attachment_ulid }}</p></div>
                <AppButton
                    type="submit"
                    variant="principal"
                    :busy="form.processing"
                    >Créer la tâche</AppButton
                >
            </form>
            <ul v-if="tasks.data.length" class="grid min-w-0 gap-3">
                <li
                    v-for="task in tasks.data"
                    :key="task.id"
                    class="grid min-w-0 gap-2 rounded-xl border border-separator p-4"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <Link
                            :href="`/taches/${task.id}`"
                            class="break-words font-semibold text-primary"
                            >{{ task.title }}</Link
                        ><span>{{ task.status_label }}</span>
                    </div>
                    <p>
                        {{ task.assignee }} · {{ task.due_date }} ·
                        {{ task.priority_label }}
                    </p>
                </li>
            </ul>
            <EmptyState
                v-else
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
            /></div
    ></AppLayout>
</template>
