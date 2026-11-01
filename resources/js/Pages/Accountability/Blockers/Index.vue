<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    blockers: { type: Array, required: true },
    users: { type: Array, required: true },
    urgencies: { type: Array, required: true },
    origins: { type: Array, default: () => [] },
    today: { type: String, default: () => new Date().toISOString().slice(0, 10) },
});

const createForm = useForm({
    origin_type: '',
    origin_id: '',
    problem: '',
    urgency: 'normale',
    solicited_user_id: '',
    reported_on: props.today,
    deadline_impact: '',
    attempted_action: '',
});
// Une seule liste « Ce qui est bloqué » alimente le type et le numéro attendus par le serveur.
const originKey = computed({
    get: () => (createForm.origin_type && createForm.origin_id ? `${createForm.origin_type}:${createForm.origin_id}` : ''),
    set: (value) => {
        const [type, id] = value ? value.split(':') : ['', ''];
        createForm.origin_type = type;
        createForm.origin_id = id;
    },
});
const transitionForm = useForm({ state: '', closure_reason: '' });
const closureBlockerId = ref(null);

function createBlocker() {
    createForm.post('/blocages', { preserveScroll: true, onSuccess: () => createForm.reset() });
}

function transition(blocker, state) {
    transitionForm.state = state;
    transitionForm.patch(`/blocages/${blocker.id}/etat`, {
        preserveScroll: true,
        onSuccess: () => {
            closureBlockerId.value = null;
            transitionForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Blocages" />
    <AppLayout title="Blocages" active-navigation="blockers">
        <div class="grid min-w-0 gap-5">
            <header>
                <h1 class="text-screen-title">Blocages</h1>
                <p class="text-ink-secondary">Signalez un obstacle et demandez une aide précise.</p>
            </header>

            <form class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-2" @submit.prevent="createBlocker">
                <h2 class="text-section-title sm:col-span-2">Signaler un blocage</h2>
                <label class="grid min-w-0 gap-1 font-semibold sm:col-span-2">Ce qui est bloqué
                    <select v-model="originKey" required class="touch-target min-w-0 rounded-lg border border-separator px-3">
                        <option value="">Choisir une tâche, un objectif ou un rapport</option>
                        <option v-for="origin in origins" :key="`${origin.type}:${origin.id}`" :value="`${origin.type}:${origin.id}`">{{ origin.label }}</option>
                    </select>
                    <span v-if="origins.length === 0" class="text-sm font-normal text-ink-secondary">Aucune tâche en cours, aucun objectif ouvert ni rapport à rattacher pour l’instant.</span>
                </label>
                <label class="grid gap-1 font-semibold sm:col-span-2">Problème
                    <textarea v-model="createForm.problem" rows="3" required class="rounded-lg border border-separator p-3" />
                </label>
                <label class="grid gap-1 font-semibold">Urgence
                    <select v-model="createForm.urgency" class="touch-target rounded-lg border border-separator px-3">
                        <option v-for="urgency in urgencies" :key="urgency.value" :value="urgency.value">{{ urgency.label }}</option>
                    </select>
                </label>
                <label class="grid gap-1 font-semibold">Personne sollicitée
                    <select v-model="createForm.solicited_user_id" required class="touch-target rounded-lg border border-separator px-3">
                        <option value="">Choisir</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </label>
                <label class="grid gap-1 font-semibold">Effet sur l’échéance
                    <textarea v-model="createForm.deadline_impact" required class="rounded-lg border border-separator p-3" />
                </label>
                <label class="grid gap-1 font-semibold">Action déjà essayée
                    <textarea v-model="createForm.attempted_action" required class="rounded-lg border border-separator p-3" />
                </label>
                <ul v-if="Object.keys(createForm.errors).length" class="grid gap-1 sm:col-span-2" role="alert">
                    <li v-for="message in new Set(Object.values(createForm.errors))" :key="message" class="text-danger">{{ message }}</li>
                </ul>
                <button class="touch-target rounded-lg bg-primary px-4 font-bold text-white sm:col-span-2" :disabled="createForm.processing">Signaler le blocage</button>
            </form>

            <section class="grid gap-3">
                <h2 class="text-section-title">Blocages ouverts</h2>
                <p v-if="blockers.length === 0" class="rounded-xl bg-neutral-soft p-4">Aucun blocage ouvert.</p>
                <ul v-else class="grid gap-3">
                    <li v-for="blocker in blockers" :key="blocker.id" class="grid gap-3 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex flex-wrap justify-between gap-2">
                            <strong class="break-words">{{ blocker.problem }}</strong>
                            <span>{{ blocker.urgency_label }} · {{ blocker.state_label }}</span>
                        </div>
                        <p>Signalé par {{ blocker.creator }} à {{ blocker.solicited }} le {{ blocker.reported_on }}</p>
                        <p><strong>Effet :</strong> {{ blocker.deadline_impact }}</p>
                        <p><strong>Déjà essayé :</strong> {{ blocker.attempted_action }}</p>
                        <p v-if="blocker.acknowledgement_delay_minutes !== null">Pris en charge après {{ blocker.acknowledgement_delay_minutes }} min</p>
                        <p v-if="blocker.resolution_delay_minutes !== null">Résolu après {{ blocker.resolution_delay_minutes }} min</p>
                        <div v-if="blocker.can_transition" class="flex flex-wrap gap-2">
                            <button v-if="blocker.state === 'ouvert'" type="button" class="touch-target rounded-lg bg-primary px-3 font-semibold text-white" @click="transition(blocker, 'pris_en_charge')">Prendre en charge</button>
                            <button v-if="blocker.state === 'pris_en_charge'" type="button" class="touch-target rounded-lg bg-primary px-3 font-semibold text-white" @click="transition(blocker, 'resolu')">Marquer résolu</button>
                            <button type="button" class="touch-target rounded-lg border border-danger px-3 font-semibold text-danger" @click="closureBlockerId = blocker.id">Fermer sans solution</button>
                        </div>
                        <form v-if="blocker.can_transition && closureBlockerId === blocker.id" class="grid gap-2" @submit.prevent="transition(blocker, 'ferme_sans_solution')">
                            <label class="font-semibold">Motif
                                <textarea v-model="transitionForm.closure_reason" required class="mt-1 w-full rounded-lg border border-separator p-3" />
                            </label>
                            <p v-for="(message, key) in transitionForm.errors" :key="key" class="text-danger" role="alert">{{ message }}</p>
                            <button class="touch-target rounded-lg bg-danger px-3 font-bold text-white">Confirmer</button>
                        </form>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
