<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppButton from '../../../Components/AppButton.vue';

const props = defineProps({
    internship: { type: Object, required: true },
    permissions: { type: Object, required: true },
    success: { type: String, default: null },
});

const planForm = useForm({
    skills_to_learn: props.internship.plan?.skills_to_learn ?? '',
    objectives: props.internship.plan?.objectives ?? '',
    weekly_tasks: props.internship.plan?.weekly_tasks ?? '',
    expected_evidence: props.internship.plan?.expected_evidence ?? '',
});

const evaluationForm = useForm({
    type: 'hebdomadaire',
    week_start_date: '',
    observed_progress: '',
    evidence: '',
    next_steps: '',
});

const exitForm = useForm({});

function savePlan() {
    planForm.put(`/stages/${props.internship.id}/plan`, { preserveScroll: true });
}

function recordEvaluation() {
    evaluationForm.post(`/stages/${props.internship.id}/evaluations`, {
        preserveScroll: true,
        onSuccess: () => evaluationForm.reset('observed_progress', 'evidence', 'next_steps'),
    });
}

function validateEvaluation(id) {
    useForm({}).patch(`/stages/${props.internship.id}/evaluations/${id}/valider`, { preserveScroll: true });
}

function endInternship() {
    exitForm.patch(`/stages/${props.internship.id}/sortie`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Stage de ${internship.intern}`" />
    <AppLayout :title="`Stage de ${internship.intern}`" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <header class="grid gap-1">
                <h1 class="text-screen-title">Stage de {{ internship.intern }}</h1>
                <p class="text-ink-secondary">Tuteur : {{ internship.tutor }} — {{ internship.state_label }}.</p>
                <p class="text-ink-secondary">
                    Depuis le {{ internship.start_date }}<template v-if="internship.end_date">, terminé le {{ internship.end_date }}</template>.
                </p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Plan de stage</h2>
                <template v-if="internship.plan">
                    <div class="grid gap-1">
                        <h3 class="font-semibold">Compétences à apprendre</h3>
                        <p class="text-ink-secondary">{{ internship.plan.skills_to_learn }}</p>
                    </div>
                    <div class="grid gap-1">
                        <h3 class="font-semibold">Objectifs</h3>
                        <p class="text-ink-secondary">{{ internship.plan.objectives }}</p>
                    </div>
                    <div class="grid gap-1">
                        <h3 class="font-semibold">Tâches hebdomadaires</h3>
                        <p class="text-ink-secondary">{{ internship.plan.weekly_tasks }}</p>
                    </div>
                    <div class="grid gap-1">
                        <h3 class="font-semibold">Preuves attendues</h3>
                        <p class="text-ink-secondary">{{ internship.plan.expected_evidence }}</p>
                    </div>
                </template>
                <p v-else class="text-ink-secondary">
                    Le plan de stage n’est pas encore rédigé. Votre tuteur l’enregistrera au démarrage.
                </p>

                <form v-if="permissions.manage" class="grid gap-2 border-t border-separator pt-3" @submit.prevent="savePlan">
                    <label class="grid gap-1 font-semibold" for="skills">Compétences à apprendre
                        <textarea id="skills" v-model="planForm.skills_to_learn" rows="2" required class="rounded-lg border border-separator p-3" />
                    </label>
                    <label class="grid gap-1 font-semibold" for="objectives">Objectifs
                        <textarea id="objectives" v-model="planForm.objectives" rows="2" required class="rounded-lg border border-separator p-3" />
                    </label>
                    <label class="grid gap-1 font-semibold" for="weekly-tasks">Tâches hebdomadaires
                        <textarea id="weekly-tasks" v-model="planForm.weekly_tasks" rows="2" required class="rounded-lg border border-separator p-3" />
                    </label>
                    <label class="grid gap-1 font-semibold" for="expected-evidence">Preuves attendues
                        <textarea id="expected-evidence" v-model="planForm.expected_evidence" rows="2" required class="rounded-lg border border-separator p-3" />
                    </label>
                    <AppButton type="submit" :disabled="planForm.processing">Enregistrer le plan</AppButton>
                </form>
            </section>

            <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Évaluations</h2>
                <p v-if="!internship.evaluations.length" class="text-ink-secondary">
                    Aucune évaluation enregistrée pour l’instant.
                </p>
                <article
                    v-for="evaluation in internship.evaluations"
                    :key="evaluation.id"
                    class="grid gap-1 border-t border-separator pt-3 first:border-t-0 first:pt-0"
                >
                    <h3 class="font-semibold">
                        {{ evaluation.type_label }}
                        <template v-if="evaluation.week_start_date"> — semaine du {{ evaluation.week_start_date }}</template>
                    </h3>
                    <p class="text-ink-secondary">Par {{ evaluation.evaluator }}, le {{ evaluation.created_at }}.</p>
                    <p class="text-ink-secondary">{{ evaluation.observed_progress }}</p>
                    <p v-if="evaluation.evidence" class="text-ink-secondary">Preuve : {{ evaluation.evidence }}</p>
                    <p v-if="evaluation.next_steps" class="text-ink-secondary">Suite : {{ evaluation.next_steps }}</p>
                    <p class="text-ink-secondary">{{ evaluation.validated ? 'Validée, non modifiable.' : 'Non validée.' }}</p>
                    <AppButton
                        v-if="permissions.manage && !evaluation.validated"
                        type="button"
                        variant="secondaire"
                        @click="validateEvaluation(evaluation.id)"
                    >
                        Valider cette évaluation
                    </AppButton>
                </article>

                <form v-if="permissions.manage" class="grid gap-2 border-t border-separator pt-3" @submit.prevent="recordEvaluation">
                    <h3 class="font-semibold">Enregistrer une évaluation</h3>
                    <label class="grid gap-1 font-semibold" for="evaluation-type">Type
                        <select id="evaluation-type" v-model="evaluationForm.type" class="touch-target rounded-lg border border-separator px-3">
                            <option value="hebdomadaire">Évaluation hebdomadaire</option>
                            <option value="finale">Évaluation finale</option>
                        </select>
                    </label>
                    <label v-if="evaluationForm.type === 'hebdomadaire'" class="grid gap-1 font-semibold" for="week-start">Semaine évaluée
                        <input id="week-start" v-model="evaluationForm.week_start_date" type="date" class="touch-target rounded-lg border border-separator px-3" />
                    </label>
                    <label class="grid gap-1 font-semibold" for="observed-progress">Progrès constatés
                        <textarea
                            id="observed-progress"
                            v-model="evaluationForm.observed_progress"
                            rows="3"
                            required
                            class="rounded-lg border border-separator p-3"
                            :aria-describedby="evaluationForm.errors.observed_progress ? 'observed-progress-error' : undefined"
                        />
                    </label>
                    <p v-if="evaluationForm.errors.observed_progress" id="observed-progress-error" class="text-danger" role="alert">
                        {{ evaluationForm.errors.observed_progress }}
                    </p>
                    <label class="grid gap-1 font-semibold" for="evaluation-evidence">Preuve
                        <textarea id="evaluation-evidence" v-model="evaluationForm.evidence" rows="2" class="rounded-lg border border-separator p-3" />
                    </label>
                    <label class="grid gap-1 font-semibold" for="evaluation-next">Prochaines étapes
                        <textarea id="evaluation-next" v-model="evaluationForm.next_steps" rows="2" class="rounded-lg border border-separator p-3" />
                    </label>
                    <AppButton type="submit" :disabled="evaluationForm.processing">Enregistrer l’évaluation</AppButton>
                </form>
            </section>

            <!-- L'application indique l'éligibilité à l'attestation, sans produire de document. -->
            <section class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Attestation</h2>
                <p class="text-ink-secondary">
                    {{ internship.certificate_conditions_met
                        ? 'Les conditions d’attestation sont remplies.'
                        : 'Les conditions d’attestation ne sont pas encore remplies.' }}
                </p>
                <p class="text-ink-secondary">Aucun document n’est produit par l’application à ce stade.</p>
            </section>

            <section
                v-for="(items, type) in internship.checklists"
                v-show="items.length"
                :key="type"
                class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4"
            >
                <h2 class="text-section-title">{{ type === 'integration' ? 'Checklist d’intégration' : 'Checklist de sortie' }}</h2>
                <ul class="grid gap-1">
                    <li v-for="item in items" :key="item.id" class="text-ink-secondary">
                        {{ item.label }} — {{ item.completed ? 'fait' : 'à faire' }}
                    </li>
                </ul>
            </section>

            <form v-if="permissions.manage && internship.state === 'actif'" @submit.prevent="endInternship">
                <AppButton type="submit" variant="secondaire" :disabled="exitForm.processing">
                    Terminer le stage et générer la checklist de sortie
                </AppButton>
            </form>
        </div>
    </AppLayout>
</template>
