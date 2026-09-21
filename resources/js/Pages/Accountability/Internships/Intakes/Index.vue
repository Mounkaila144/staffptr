<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import AppButton from '../../../../Components/AppButton.vue';
import EmptyState from '../../../../Components/EmptyState.vue';
import FormField from '../../../../Components/FormField.vue';

defineProps({
    forms: { type: Object, required: true },
    canCreate: { type: Boolean, default: false },
    candidates: { type: Array, default: () => [] },
    managers: { type: Array, default: () => [] },
    tutors: { type: Array, default: () => [] },
    success: { type: String, default: null },
});

const drafting = ref(false);

// Trois résultats attendus au départ : c'est le minimum qu'exige le serveur, et commencer
// en dessous ferait découvrir la règle par une erreur plutôt que par le formulaire.
const form = useForm({
    candidate_user_id: '',
    manager_id: '',
    tutor_id: '',
    real_need: '',
    mission: '',
    duration_weeks: 12,
    tools: '',
    outcomes: ['', '', ''],
});

function addOutcome() {
    if (form.outcomes.length < 10) {
        form.outcomes.push('');
    }
}

function removeOutcome(index) {
    if (form.outcomes.length > 3) {
        form.outcomes.splice(index, 1);
    }
}

function submit() {
    form.post('/stages/fiches-entree', { preserveScroll: true });
}
</script>

<template>
    <Head title="Fiches d’entrée" />
    <AppLayout title="Fiches d’entrée" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <header class="grid gap-2">
                <h1 class="text-screen-title">Fiches d’entrée</h1>
                <p class="text-ink-secondary">
                    Une fiche précise le besoin réel, la mission, le tuteur et les trois résultats attendus.
                </p>
                <p class="text-ink-secondary">
                    C’est la première étape avant d’activer un compte de stagiaire : une fois la fiche approuvée,
                    le tuteur désigné et trois objectifs enregistrés, le bouton d’activation apparaît sur la fiche.
                </p>
                <div v-if="canCreate && !drafting">
                    <AppButton variant="principal" @click="drafting = true">Rédiger une fiche</AppButton>
                </div>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <section v-if="canCreate && drafting" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Rédiger une fiche d’entrée</h2>
                <p v-if="candidates.length === 0" class="text-ink-secondary">
                    Aucun compte de stagiaire n’existe encore. Créez-le d’abord depuis « Comptes et rôles »,
                    puis revenez rédiger sa fiche.
                </p>

                <form v-else class="grid min-w-0 gap-4" @submit.prevent="submit">
                    <FormField
                        id="candidate-user-id"
                        v-model="form.candidate_user_id"
                        as="select"
                        label="Compte du stagiaire"
                        placeholder="Choisissez un compte"
                        required
                        :options="candidates"
                        :error="form.errors.candidate_user_id"
                    />
                    <FormField
                        id="manager-id"
                        v-model="form.manager_id"
                        as="select"
                        label="Responsable du stage"
                        placeholder="Choisissez un responsable"
                        hint="La personne qui porte le besoin auquel ce stage répond."
                        required
                        :options="managers"
                        :error="form.errors.manager_id"
                    />
                    <FormField
                        id="tutor-id"
                        v-model="form.tutor_id"
                        as="select"
                        label="Tuteur"
                        placeholder="Choisissez un tuteur"
                        hint="Sans tuteur désigné, le compte ne pourra pas être activé."
                        required
                        :options="tutors"
                        :error="form.errors.tutor_id"
                    />
                    <FormField
                        id="real-need"
                        v-model="form.real_need"
                        as="textarea"
                        label="Besoin réel"
                        hint="Le travail qui resterait à faire si ce stage n’existait pas."
                        required
                        :error="form.errors.real_need"
                    />
                    <FormField
                        id="mission"
                        v-model="form.mission"
                        as="textarea"
                        label="Mission confiée"
                        required
                        :error="form.errors.mission"
                    />
                    <FormField
                        id="duration-weeks"
                        v-model="form.duration_weeks"
                        variant="number"
                        label="Durée en semaines"
                        required
                        :error="form.errors.duration_weeks"
                    />
                    <FormField
                        id="tools"
                        v-model="form.tools"
                        as="textarea"
                        label="Outils mis à disposition"
                        required
                        :error="form.errors.tools"
                    />

                    <fieldset class="grid min-w-0 gap-3">
                        <legend class="text-field-label font-semibold">Résultats attendus — trois au minimum</legend>
                        <p v-if="form.errors.outcomes" class="text-sm font-semibold text-danger">⚠ {{ form.errors.outcomes }}</p>
                        <div v-for="(outcome, index) in form.outcomes" :key="index" class="grid min-w-0 gap-2">
                            <FormField
                                :id="`outcome-${index}`"
                                v-model="form.outcomes[index]"
                                as="textarea"
                                :rows="2"
                                :label="`Résultat attendu ${index + 1}`"
                                required
                                :error="form.errors[`outcomes.${index}`]"
                            />
                            <div v-if="form.outcomes.length > 3">
                                <AppButton variant="secondaire" @click="removeOutcome(index)">
                                    Retirer le résultat {{ index + 1 }}
                                </AppButton>
                            </div>
                        </div>
                        <div v-if="form.outcomes.length < 10">
                            <AppButton variant="secondaire" @click="addOutcome">Ajouter un résultat attendu</AppButton>
                        </div>
                    </fieldset>

                    <div class="flex flex-wrap gap-2">
                        <!-- Le bouton se désactive sans changer de libellé ni de largeur : pendant
                             l'envoi, on doit continuer de lire ce qu'on est en train de faire. -->
                        <AppButton type="submit" variant="principal" :disabled="form.processing">
                            Enregistrer le brouillon
                        </AppButton>
                        <AppButton variant="secondaire" @click="drafting = false">Annuler</AppButton>
                    </div>
                </form>
            </section>

            <ul v-if="forms.data.length" class="grid min-w-0 gap-3">
                <li v-for="entry in forms.data" :key="entry.id">
                    <button
                        type="button"
                        class="touch-target grid w-full min-w-0 gap-1 rounded-xl border border-separator bg-surface p-4 text-left"
                        @click="router.visit(`/stages/fiches-entree/${entry.id}`)"
                    >
                        <span class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-card-title">{{ entry.candidate }}</span>
                            <span class="text-sm font-semibold text-ink-secondary">{{ entry.state_label }}</span>
                        </span>
                        <span class="text-ink-secondary">Tuteur : {{ entry.tutor || 'à désigner' }}</span>
                        <span class="text-ink-secondary">{{ entry.duration_weeks }} semaines — {{ entry.outcomes.length }} résultats attendus</span>
                    </button>
                </li>
            </ul>

            <EmptyState
                v-else-if="!drafting"
                title="Aucune fiche d’entrée"
                reason="Aucune fiche d’entrée ne vous concerne pour l’instant."
                :action-label="canCreate ? 'Rédiger une fiche' : ''"
                @action="drafting = true"
            />
        </div>
    </AppLayout>
</template>
