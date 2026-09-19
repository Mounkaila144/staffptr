<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppButton from '../../../Components/AppButton.vue';

const props = defineProps({
    review: { type: Object, required: true },
    facts: { type: Object, required: true },
    permissions: { type: Object, required: true },
    success: { type: String, default: null },
});

const commentForm = useForm({ body: '' });
const validationForm = useForm({});

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
                    <p v-if="commentForm.errors.body" id="weekly-review-comment-error" class="text-danger" role="alert">
                        {{ commentForm.errors.body }}
                    </p>
                    <AppButton type="submit" :disabled="commentForm.processing">Enregistrer le commentaire</AppButton>
                </form>
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

                <form v-if="permissions.validate" @submit.prevent="validateReview">
                    <AppButton type="submit" :disabled="validationForm.processing">Valider cette revue</AppButton>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
