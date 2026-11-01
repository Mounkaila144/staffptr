<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppButton from '../../../Components/AppButton.vue';

const props = defineProps({
    review: { type: Object, required: true },
    facts: { type: Object, required: true },
    permissions: { type: Object, required: true },
    objectiveStatuses: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
    success: { type: String, default: null },
});

const commentForm = useForm({ body: '' });
const validationForm = useForm({});
const submitForm = useForm({});
const objectiveForm = useForm({ objective_id: '', result: '', evidence: '', status: '', gap_cause: '', next_action: '' });
const planForm = useForm({ start_date: '', end_date: '', support_provided: '', actions: [{ description: '', due_date: '' }] });
const needsGapCause = computed(() => props.objectiveStatuses.find((status) => status.value === objectiveForm.status)?.requires_gap_cause ?? false);

// Choisir un objectif déjà passé en revue reprend son constat pour le corriger.
function pickObjective() {
    const entry = props.review.objective_entries.find((item) => item.objective_id === Number(objectiveForm.objective_id));
    objectiveForm.result = entry?.result ?? '';
    objectiveForm.evidence = entry?.evidence ?? '';
    objectiveForm.status = entry?.status ?? '';
    objectiveForm.gap_cause = entry?.gap_cause ?? '';
    objectiveForm.next_action = entry?.next_action ?? '';
}

function recordObjective() {
    objectiveForm.post(`/revues-hebdomadaires/${props.review.id}/objectifs`, {
        preserveScroll: true,
        onSuccess: () => {
            objectiveForm.reset();
            submitForm.clearErrors();
        },
    });
}

function submitReview() {
    submitForm.patch(`/revues-hebdomadaires/${props.review.id}/soumettre`, { preserveScroll: true });
}

function addAction() {
    planForm.actions.push({ description: '', due_date: '' });
}

function removeAction(index) {
    planForm.actions.splice(index, 1);
}

function createPlan() {
    planForm.post(`/revues-hebdomadaires/${props.review.id}/plans-accompagnement`, { preserveScroll: true });
}

function submitComment() {
    commentForm.post(`/revues-hebdomadaires/${props.review.id}/commentaires`, {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
}

function validateReview() {
    validationForm.patch(`/revues-hebdomadaires/${props.review.id}/valider`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Revue de ${review.subject}`" />
    <AppLayout :title="`Revue de ${review.subject}`" active-navigation="weekly-reviews">
        <div class="grid min-w-0 gap-5">
            <header class="grid gap-1">
                <h1 class="text-screen-title">Revue de {{ review.subject }}</h1>
                <p class="text-ink-secondary">Semaine du {{ review.week_start_date }}, tenue le {{ review.scheduled_on }}.</p>
                <p class="text-ink-secondary">Conduite par {{ review.reviewer }} — {{ review.state_label }}.</p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <p v-if="!review.is_editable" class="rounded-lg border border-separator bg-neutral-soft p-3">
                Cette revue est validée par les deux parties. Elle reste consultable et n’est plus modifiable.
            </p>

            <!-- Faits de la semaine, rassemblés sans ressaisie. Ils ne concernent que la personne
                 évaluée : aucun classement ni comparaison avec une autre personne. -->
            <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Faits de la semaine</h2>

                <div class="grid gap-1">
                    <h3 class="font-semibold">Objectifs</h3>
                    <p v-if="!facts.objectives.length" class="text-ink-secondary">Aucun objectif sur cette semaine.</p>
                    <ul v-else class="grid gap-1">
                        <li v-for="objective in facts.objectives" :key="objective.id" class="text-ink-secondary">
                            {{ objective.title }} — {{ objective.state_label }}
                        </li>
                    </ul>
                </div>

                <div class="grid gap-1">
                    <h3 class="font-semibold">Tâches</h3>
                    <p v-if="!facts.tasks.length" class="text-ink-secondary">Aucune tâche échue sur cette semaine.</p>
                    <ul v-else class="grid gap-1">
                        <li v-for="task in facts.tasks" :key="task.id" class="text-ink-secondary">
                            {{ task.title }} — {{ task.status_label }}
                        </li>
                    </ul>
                </div>

                <div class="grid gap-1">
                    <h3 class="font-semibold">Rapports quotidiens</h3>
                    <p v-if="!facts.daily_reports.length" class="text-ink-secondary">Aucun rapport sur cette semaine.</p>
                    <ul v-else class="grid gap-1">
                        <li v-for="report in facts.daily_reports" :key="report.id" class="text-ink-secondary">
                            {{ report.date }} — {{ report.state_label }}
                        </li>
                    </ul>
                </div>

                <div class="grid gap-1">
                    <h3 class="font-semibold">Blocages</h3>
                    <p v-if="!facts.blockers.length" class="text-ink-secondary">Aucun blocage signalé sur cette semaine.</p>
                    <ul v-else class="grid gap-1">
                        <li v-for="blocker in facts.blockers" :key="blocker.id" class="text-ink-secondary">
                            {{ blocker.reported_on }} — {{ blocker.problem }}
                        </li>
                    </ul>
                </div>
            </section>

            <form v-if="permissions.update" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-2" @submit.prevent="recordObjective">
                <h2 class="text-section-title sm:col-span-2">Passer un objectif en revue</h2>
                <p v-if="!facts.objectives.length" class="text-ink-secondary sm:col-span-2">
                    {{ review.subject }} n’a aucun objectif ouvert ou échu cette semaine : il n’y a rien à passer en revue.
                </p>
                <template v-else>
                    <label class="grid min-w-0 gap-1 font-semibold sm:col-span-2" for="review-objective">Objectif
                        <select id="review-objective" v-model="objectiveForm.objective_id" required class="touch-target min-w-0 rounded-lg border border-separator px-3" @change="pickObjective">
                            <option value="">Choisir</option>
                            <option v-for="objective in facts.objectives" :key="objective.id" :value="objective.id">{{ objective.title }} — échéance {{ objective.due_date }}</option>
                        </select>
                    </label>
                    <label class="grid min-w-0 gap-1 font-semibold sm:col-span-2" for="review-result">Résultat constaté
                        <textarea id="review-result" v-model="objectiveForm.result" rows="3" required class="min-w-0 rounded-lg border border-separator p-3" />
                    </label>
                    <label class="grid min-w-0 gap-1 font-semibold" for="review-status">Où en est l’objectif ?
                        <select id="review-status" v-model="objectiveForm.status" required class="touch-target min-w-0 rounded-lg border border-separator px-3">
                            <option value="">Choisir</option>
                            <option v-for="status in objectiveStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </label>
                    <label class="grid min-w-0 gap-1 font-semibold" for="review-evidence">Preuve (facultatif)
                        <input id="review-evidence" v-model="objectiveForm.evidence" type="text" class="touch-target min-w-0 rounded-lg border border-separator px-3" placeholder="Lien ou description" />
                    </label>
                    <label v-if="needsGapCause" class="grid min-w-0 gap-1 font-semibold sm:col-span-2" for="review-gap">Cause de l’écart
                        <textarea id="review-gap" v-model="objectiveForm.gap_cause" rows="2" required class="min-w-0 rounded-lg border border-separator p-3" />
                    </label>
                    <label class="grid min-w-0 gap-1 font-semibold sm:col-span-2" for="review-next">Prochaine action convenue
                        <textarea id="review-next" v-model="objectiveForm.next_action" rows="2" required class="min-w-0 rounded-lg border border-separator p-3" />
                    </label>
                    <ul v-if="Object.keys(objectiveForm.errors).length" class="grid gap-1 sm:col-span-2" role="alert">
                        <li v-for="(message, key) in objectiveForm.errors" :key="key" class="text-danger">{{ message }}</li>
                    </ul>
                    <AppButton type="submit" variant="principal" class="sm:col-span-2" :disabled="objectiveForm.processing">Enregistrer le constat</AppButton>
                </template>
            </form>

            <section v-if="review.objective_entries.length" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Constats par objectif</h2>
                <article v-for="entry in review.objective_entries" :key="entry.id" class="grid gap-1 border-t border-separator pt-3 first:border-t-0 first:pt-0">
                    <h3 class="font-semibold">{{ entry.objective_title }}</h3>
                    <p class="text-ink-secondary">Résultat : {{ entry.result }}</p>
                    <p v-if="entry.evidence" class="text-ink-secondary">Preuve : {{ entry.evidence }}</p>
                    <p class="text-ink-secondary">Statut : {{ entry.status_label }}</p>
                    <p v-if="entry.gap_cause" class="text-ink-secondary">Cause de l’écart : {{ entry.gap_cause }}</p>
                    <p class="text-ink-secondary">Prochaine action : {{ entry.next_action }}</p>
                </article>
            </section>

            <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Commentaires</h2>
                <div class="grid gap-1">
                    <h3 class="font-semibold">{{ review.subject }}</h3>
                    <p class="text-ink-secondary">{{ review.reviewee_comment || 'Aucun commentaire pour l’instant.' }}</p>
                </div>
                <div class="grid gap-1">
                    <h3 class="font-semibold">{{ review.reviewer }}</h3>
                    <p class="text-ink-secondary">{{ review.reviewer_comment || 'Aucun commentaire pour l’instant.' }}</p>
                </div>

                <form v-if="permissions.comment" class="grid gap-2" @submit.prevent="submitComment">
                    <label class="grid gap-1 font-semibold" for="weekly-review-comment">Votre commentaire
                        <textarea
                            id="weekly-review-comment"
                            v-model="commentForm.body"
                            rows="4"
                            required
                            class="rounded-lg border border-separator p-3"
                            :aria-describedby="commentForm.errors.body ? 'weekly-review-comment-error' : undefined"
                        />
                    </label>
                    <p v-if="commentForm.errors.body || commentForm.errors.review" id="weekly-review-comment-error" class="text-danger" role="alert">
                        {{ commentForm.errors.body || commentForm.errors.review }}
                    </p>
                    <AppButton type="submit" :disabled="commentForm.processing">Enregistrer le commentaire</AppButton>
                </form>
            </section>

            <section v-if="permissions.submit" class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Soumettre la revue</h2>
                <p class="text-ink-secondary">Une fois les constats et commentaires saisis, soumettez la revue : {{ review.subject }} et vous-même pourrez alors la valider.</p>
                <p v-for="(message, key) in submitForm.errors" :key="key" class="text-danger" role="alert">{{ message }}</p>
                <AppButton type="button" variant="principal" :disabled="submitForm.processing" @click="submitReview">Soumettre aux deux validations</AppButton>
            </section>

            <!-- Validation électronique des deux parties : horodatée et nominative. -->
            <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Validations</h2>
                <p class="text-ink-secondary">
                    Personne évaluée :
                    <template v-if="review.reviewee_validation.validated_at">
                        validée par {{ review.reviewee_validation.validated_by }} le {{ review.reviewee_validation.validated_at }}.
                    </template>
                    <template v-else>en attente.</template>
                </p>
                <p class="text-ink-secondary">
                    Responsable :
                    <template v-if="review.reviewer_validation.validated_at">
                        validée par {{ review.reviewer_validation.validated_by }} le {{ review.reviewer_validation.validated_at }}.
                    </template>
                    <template v-else>en attente.</template>
                </p>

                <form v-if="permissions.validate && review.state === 'en_attente_validation'" class="grid gap-2" @submit.prevent="validateReview">
                    <p v-for="(message, key) in validationForm.errors" :key="key" class="text-danger" role="alert">{{ message }}</p>
                    <AppButton type="submit" :disabled="validationForm.processing">Valider cette revue</AppButton>
                </form>
                <p v-else-if="permissions.validate && review.state === 'brouillon'" class="text-sm text-ink-secondary">La validation s’ouvre lorsque le responsable a soumis la revue.</p>
            </section>

            <section v-if="plans.length || permissions.createPlan" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Plan d’accompagnement</h2>
                <ul v-if="plans.length" class="grid gap-2">
                    <li v-for="plan in plans" :key="plan.id">
                        <Link :href="`/plans-accompagnement/${plan.id}`" class="touch-target inline-flex items-center font-semibold text-primary underline">{{ plan.period }} — {{ plan.state_label }}</Link>
                    </li>
                </ul>
                <form v-if="permissions.createPlan" class="grid min-w-0 gap-3 sm:grid-cols-2" @submit.prevent="createPlan">
                    <p class="text-ink-secondary sm:col-span-2">Si la revue montre un besoin d’aide, convenez d’un accompagnement de 7 à 14 jours avec des actions datées.</p>
                    <label class="grid min-w-0 gap-1 font-semibold" for="plan-start">Début
                        <input id="plan-start" v-model="planForm.start_date" type="date" required class="touch-target min-w-0 rounded-lg border border-separator px-3" />
                    </label>
                    <label class="grid min-w-0 gap-1 font-semibold" for="plan-end">Fin
                        <input id="plan-end" v-model="planForm.end_date" type="date" required class="touch-target min-w-0 rounded-lg border border-separator px-3" />
                    </label>
                    <label class="grid min-w-0 gap-1 font-semibold sm:col-span-2" for="plan-support">Aide fournie
                        <textarea id="plan-support" v-model="planForm.support_provided" rows="3" required class="min-w-0 rounded-lg border border-separator p-3" />
                    </label>
                    <fieldset class="grid min-w-0 gap-3 sm:col-span-2">
                        <legend class="font-semibold">Actions convenues</legend>
                        <div v-for="(action, index) in planForm.actions" :key="index" class="grid min-w-0 gap-2 rounded-lg border border-separator p-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                            <label class="grid min-w-0 gap-1 font-semibold" :for="`plan-action-${index}`">Action {{ index + 1 }}
                                <input :id="`plan-action-${index}`" v-model="action.description" type="text" required class="touch-target min-w-0 rounded-lg border border-separator px-3" />
                            </label>
                            <label class="grid min-w-0 gap-1 font-semibold" :for="`plan-action-date-${index}`">Prévue le
                                <input :id="`plan-action-date-${index}`" v-model="action.due_date" type="date" required class="touch-target min-w-0 rounded-lg border border-separator px-3" />
                            </label>
                            <AppButton v-if="planForm.actions.length > 1" variant="discret" @click="removeAction(index)">Retirer</AppButton>
                        </div>
                        <AppButton variant="secondaire" class="w-fit" @click="addAction">Ajouter une action</AppButton>
                    </fieldset>
                    <ul v-if="Object.keys(planForm.errors).length" class="grid gap-1 sm:col-span-2" role="alert">
                        <li v-for="(message, key) in planForm.errors" :key="key" class="text-danger">{{ message }}</li>
                    </ul>
                    <AppButton type="submit" variant="principal" class="sm:col-span-2" :disabled="planForm.processing">Mettre en place l’accompagnement</AppButton>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
