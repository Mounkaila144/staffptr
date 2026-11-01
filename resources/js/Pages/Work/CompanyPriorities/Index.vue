<script setup>
import { computed, reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
const props = defineProps({ month: String, monthLabel: String, priorities: Array, canManage: Boolean, emptyMessage: String, priorityOptions: Array, assignablePeople: { type: Array, default: () => [] } });
const peopleOptions = computed(() => props.assignablePeople.map((person) => ({ value: person.id, label: person.name })));
const form = useForm({ month: `${props.month}-01`, title: '', description: '', owner_id: '', indicator: '', target: '', due_date: '', priority: 'normale' });
const submit = () => form.post('/priorites-entreprise', { preserveScroll: true, onSuccess: () => form.reset('title', 'description', 'indicator', 'target') });

// Une fiche de modification par priorité, préremplie ; le motif est exigé par le serveur.
const edits = reactive({});
const cancels = reactive({});
const editFor = (item) => (edits[item.id] ??= useForm({ month: `${props.month}-01`, title: item.title, description: item.description, owner_id: item.owner_id, indicator: item.indicator, target: item.target, due_date: item.due_date_value, priority: item.priority, reason: '' }));
const cancelFor = (item) => (cancels[item.id] ??= useForm({ reason: '' }));
const update = (item) => editFor(item).patch(`/priorites-entreprise/${item.id}`, { preserveScroll: true, onSuccess: () => { delete edits[item.id]; } });
const cancel = (item) => cancelFor(item).patch(`/priorites-entreprise/${item.id}/annuler`, { preserveScroll: true, onSuccess: () => { delete cancels[item.id]; } });
const scrollToForm = () => document.getElementById('priority-title')?.focus();
</script>
<template>
    <Head title="Priorités d’entreprise" />
    <AppLayout title="Priorités d’entreprise">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Priorités d’entreprise — {{ monthLabel }}</h1>
                <p class="text-ink-secondary">Au maximum cinq priorités validées structurent le mois.</p>
            </header>
            <form v-if="canManage" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">Créer une priorité</h2>
                <p v-if="form.errors.month" class="rounded-lg border border-danger p-3 text-danger" role="alert">{{ form.errors.month }}</p>
                <FormField id="priority-title" v-model="form.title" label="Titre" :maxlength="160" :error="form.errors.title" required />
                <FormField id="priority-description" v-model="form.description" as="textarea" label="Description courte" :maxlength="500" :rows="3" :error="form.errors.description" required />
                <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                    <FormField id="priority-owner" v-model="form.owner_id" as="select" label="Responsable" placeholder="Choisir une personne" :options="peopleOptions" :error="form.errors.owner_id" required />
                    <FormField id="priority-priority" v-model="form.priority" as="select" label="Priorité" :options="priorityOptions" :error="form.errors.priority" required />
                    <FormField id="priority-indicator" v-model="form.indicator" label="Indicateur" :maxlength="160" :error="form.errors.indicator" required />
                    <FormField id="priority-target" v-model="form.target" label="Cible" :maxlength="160" :error="form.errors.target" required />
                    <FormField id="priority-due-date" v-model="form.due_date" variant="date" label="Échéance" :error="form.errors.due_date" required />
                </div>
                <AppButton type="submit" variant="principal" :busy="form.processing">Créer la priorité</AppButton>
            </form>
            <ul v-if="priorities.length" class="grid min-w-0 gap-3">
                <li v-for="item in priorities" :key="item.id" class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap justify-between gap-2">
                        <h2 class="break-words font-semibold">{{ item.title }}</h2>
                        <span class="rounded-full border border-separator px-3 py-1">{{ item.state_label }}</span>
                    </div>
                    <p class="break-words">{{ item.description }}</p>
                    <p class="text-sm text-ink-secondary">{{ item.owner }} · {{ item.priority_label }} · échéance {{ item.due_date }}</p>
                    <p><strong>{{ item.indicator }} :</strong> {{ item.target }}</p>
                    <p v-if="item.cancellation_reason" class="text-sm text-ink-secondary">Motif de l’annulation : {{ item.cancellation_reason }}</p>
                    <details v-if="canManage && item.state === 'validee'" class="grid gap-3">
                        <summary class="touch-target flex cursor-pointer items-center font-semibold text-primary">Modifier ou annuler cette priorité</summary>
                        <form class="mt-3 grid min-w-0 gap-3" @submit.prevent="update(item)">
                            <h3 class="font-semibold">Modifier</h3>
                            <FormField :id="`priority-${item.id}-title`" v-model="editFor(item).title" label="Titre" :maxlength="160" :error="editFor(item).errors.title" required />
                            <FormField :id="`priority-${item.id}-description`" v-model="editFor(item).description" as="textarea" label="Description courte" :maxlength="500" :rows="3" :error="editFor(item).errors.description" required />
                            <div class="grid min-w-0 gap-3 sm:grid-cols-2">
                                <FormField :id="`priority-${item.id}-owner`" v-model="editFor(item).owner_id" as="select" label="Responsable" :options="peopleOptions" :error="editFor(item).errors.owner_id" required />
                                <FormField :id="`priority-${item.id}-priority`" v-model="editFor(item).priority" as="select" label="Priorité" :options="priorityOptions" :error="editFor(item).errors.priority" required />
                                <FormField :id="`priority-${item.id}-indicator`" v-model="editFor(item).indicator" label="Indicateur" :maxlength="160" :error="editFor(item).errors.indicator" required />
                                <FormField :id="`priority-${item.id}-target`" v-model="editFor(item).target" label="Cible" :maxlength="160" :error="editFor(item).errors.target" required />
                                <FormField :id="`priority-${item.id}-due-date`" v-model="editFor(item).due_date" variant="date" label="Échéance" :error="editFor(item).errors.due_date" required />
                            </div>
                            <FormField :id="`priority-${item.id}-reason`" v-model="editFor(item).reason" as="textarea" label="Motif de la modification" hint="Obligatoire : la priorité est déjà validée. 5 caractères au moins." :rows="2" :maxlength="1000" :error="editFor(item).errors.reason" required />
                            <AppButton type="submit" variant="principal" :busy="editFor(item).processing">Enregistrer la modification</AppButton>
                        </form>
                        <form class="mt-3 grid min-w-0 gap-3 border-t border-separator pt-3" @submit.prevent="cancel(item)">
                            <h3 class="font-semibold">Annuler</h3>
                            <FormField :id="`priority-${item.id}-cancel-reason`" v-model="cancelFor(item).reason" as="textarea" label="Motif de l’annulation" hint="5 caractères au moins. L’annulation libère une place parmi les cinq priorités du mois." :rows="2" :maxlength="1000" :error="cancelFor(item).errors.reason" required />
                            <AppButton type="submit" :busy="cancelFor(item).processing">Annuler la priorité</AppButton>
                        </form>
                    </details>
                </li>
            </ul>
            <EmptyState v-else :title="emptyMessage" reason="Les priorités apparaîtront ici dès leur définition." :action-label="canManage ? 'Créer une priorité' : undefined" @action="scrollToForm" />
        </div>
    </AppLayout>
</template>
