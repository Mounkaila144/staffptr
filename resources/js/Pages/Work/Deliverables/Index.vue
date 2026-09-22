<script setup>
import { reactive } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import EmptyState from "../../../Components/EmptyState.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({ deliverables: Object, statuses: Array, assignablePeople: { type: Array, default: () => [] }, projects: { type: Array, default: () => [] } });
const form = useForm({
    project_id: "",
    owner_id: "",
    title: "",
    planned_date: "",
    actual_date: "",
    status: "prevu",
});
const transitions = reactive(Object.fromEntries(props.deliverables.data.map((item) => [item.id, { status: "", reason: "" }])));
const transition = (item) => router.patch(`/livrables/${item.id}/etat`, transitions[item.id]);
</script>
<template>
    <Head title="Livrables" /><AppLayout title="Livrables"
        ><div class="grid min-w-0 gap-6">
            <header class="grid gap-2">
                <h1 class="text-page-title">Livrables</h1>
                <p class="text-ink-secondary">
                    Dates prévues, dates réelles et validation explicite.
                </p>
            </header>
            <form
                class="grid min-w-0 gap-3 rounded-xl border border-separator p-4 sm:grid-cols-2"
                @submit.prevent="form.post('/livrables')"
            >
                <h2 class="text-section-title sm:col-span-2">
                    Créer un livrable
                </h2>
                <input
                    v-model="form.title"
                    required
                    placeholder="Titre"
                    class="touch-target min-w-0 rounded-lg border border-separator px-3"
                /><label class="grid gap-1"
                    >Projet<select
                        v-model="form.project_id"
                        required
                        class="touch-target min-w-0 rounded-lg border border-separator bg-surface px-3"
                    >
                        <option value="" disabled>Choisir un projet</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select></label
                ><label class="grid gap-1"
                    >Responsable<select
                        v-model="form.owner_id"
                        required
                        class="touch-target min-w-0 rounded-lg border border-separator bg-surface px-3"
                    >
                        <option value="" disabled>Choisir une personne</option>
                        <option v-for="person in assignablePeople" :key="person.id" :value="person.id">
                            {{ person.name }}
                        </option>
                    </select></label
                ><input
                    v-model="form.planned_date"
                    required
                    type="date"
                    class="touch-target min-w-0 rounded-lg border border-separator px-3"
                /><input
                    v-model="form.actual_date"
                    type="date"
                    aria-label="Date réelle optionnelle"
                    class="touch-target min-w-0 rounded-lg border border-separator px-3"
                /><select
                    v-model="form.status"
                    aria-label="Statut initial"
                    class="touch-target min-w-0 rounded-lg border border-separator px-3"
                ><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select
                /><AppButton
                    type="submit"
                    variant="principal"
                    :busy="form.processing"
                    >Créer le livrable</AppButton
                >
            </form>
            <ul v-if="deliverables.data.length" class="grid min-w-0 gap-3">
                <li
                    v-for="item in deliverables.data"
                    :key="item.id"
                    class="grid min-w-0 gap-2 rounded-xl border border-separator p-4"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <strong class="break-words">{{ item.title }}</strong
                        ><span>{{ item.status_label }}</span>
                    </div>
                    <p>{{ item.project }} · {{ item.owner }}</p>
                    <p>{{ item.variance_label }}</p>
                    <form class="grid min-w-0 gap-2 sm:grid-cols-2" @submit.prevent="transition(item)">
                        <select v-model="transitions[item.id].status" required :aria-label="`Nouveau statut de ${item.title}`" class="touch-target min-w-0 rounded-lg border border-separator px-3"><option value="" disabled>Choisir un statut</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                        <input v-model="transitions[item.id].reason" required minlength="5" placeholder="Motif du changement" class="touch-target min-w-0 rounded-lg border border-separator px-3">
                        <AppButton type="submit">Mettre à jour le statut</AppButton>
                    </form>
                </li>
            </ul>
            <EmptyState
                v-else
                title="Aucun livrable prévu."
                reason="Les livrables apparaîtront ici après leur création."
            /></div
    ></AppLayout>
</template>
