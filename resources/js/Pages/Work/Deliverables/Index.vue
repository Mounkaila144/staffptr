<script setup>
import { computed, reactive } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import AppButton from "../../../Components/AppButton.vue";
import EmptyState from "../../../Components/EmptyState.vue";
import FormField from "../../../Components/FormField.vue";
import AppLayout from "../../../Layouts/AppLayout.vue";
const props = defineProps({
    deliverables: Object,
    statuses: Array,
    canCreate: { type: Boolean, default: false },
    assignablePeople: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
});
const projectOptions = computed(() => props.projects.map((p) => ({ value: p.id, label: p.name })));
const peopleOptions = computed(() => props.assignablePeople.map((p) => ({ value: p.id, label: p.name })));
const form = useForm({
    project_id: "",
    owner_id: "",
    title: "",
    planned_date: "",
    actual_date: "",
    status: "prevu",
});
const submit = () =>
    form.post("/livrables", {
        preserveScroll: true,
        onSuccess: () => form.reset("title", "planned_date", "actual_date"),
    });
// Un formulaire de changement de statut par livrable, créé à la demande pour garder ses erreurs.
const transitions = reactive({});
const transitionFor = (item) => (transitions[item.id] ??= useForm({ status: "", reason: "" }));
const transition = (item) =>
    transitionFor(item).patch(`/livrables/${item.id}/etat`, {
        preserveScroll: true,
        onSuccess: () => delete transitions[item.id],
    });
const focusForm = () => document.getElementById("deliverable-title")?.focus();
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
                v-if="canCreate"
                class="grid min-w-0 gap-4 rounded-xl border border-separator p-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <h2 class="text-section-title sm:col-span-2">
                    Créer un livrable
                </h2>
                <div class="sm:col-span-2">
                    <FormField id="deliverable-title" v-model="form.title" label="Titre" :maxlength="200" :error="form.errors.title" required />
                </div>
                <FormField id="deliverable-project" v-model="form.project_id" as="select" label="Projet" placeholder="Choisir un projet" :options="projectOptions" :error="form.errors.project_id" required />
                <FormField id="deliverable-owner" v-model="form.owner_id" as="select" label="Responsable" placeholder="Choisir une personne" :options="peopleOptions" :error="form.errors.owner_id" required />
                <FormField id="deliverable-planned-date" v-model="form.planned_date" variant="date" label="Date prévue" :error="form.errors.planned_date" required />
                <FormField id="deliverable-actual-date" v-model="form.actual_date" variant="date" label="Date réelle" hint="À renseigner si le livrable a déjà été remis." :error="form.errors.actual_date" optional-label />
                <FormField id="deliverable-status" v-model="form.status" as="select" label="Statut initial" :options="statuses" :error="form.errors.status" required />
                <div class="flex items-end">
                    <AppButton
                        type="submit"
                        variant="principal"
                        :busy="form.processing"
                        >Créer le livrable</AppButton
                    >
                </div>
            </form>
            <ul v-if="deliverables.data.length" class="grid min-w-0 gap-3">
                <li
                    v-for="item in deliverables.data"
                    :key="item.id"
                    class="grid min-w-0 gap-2 rounded-xl border border-separator p-4"
                >
                    <div class="flex flex-wrap justify-between gap-2">
                        <strong class="break-words">{{ item.title }}</strong
                        ><span class="rounded-full border border-separator px-3 py-1">{{ item.status_label }}</span>
                    </div>
                    <p>{{ item.project }} · {{ item.owner }}</p>
                    <p class="text-sm text-ink-secondary">
                        Prévu le {{ item.planned_date }}<template v-if="item.actual_date"> · remis le {{ item.actual_date }}</template> · {{ item.variance_label }}
                    </p>
                    <form v-if="item.transitions.length" class="grid min-w-0 gap-2 sm:grid-cols-2" @submit.prevent="transition(item)">
                        <FormField :id="`deliverable-${item.id}-status`" v-model="transitionFor(item).status" as="select" label="Nouveau statut" placeholder="Choisir un statut" :options="item.transitions" :error="transitionFor(item).errors.status" required />
                        <FormField :id="`deliverable-${item.id}-reason`" v-model="transitionFor(item).reason" label="Motif du changement" hint="5 caractères au moins." :maxlength="1000" :error="transitionFor(item).errors.reason" required />
                        <div><AppButton type="submit" :busy="transitionFor(item).processing">Mettre à jour le statut</AppButton></div>
                    </form>
                </li>
            </ul>
            <EmptyState
                v-else
                title="Aucun livrable prévu."
                reason="Les livrables apparaîtront ici après leur création."
                :action-label="canCreate ? 'Créer un livrable' : undefined"
                @action="focusForm"
            /></div
    ></AppLayout>
</template>
