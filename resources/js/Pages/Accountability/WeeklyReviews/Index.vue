<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { nextTick } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';

const props = defineProps({
    reviews: { type: Object, required: true },
    canOpen: { type: Boolean, default: false },
    subjects: { type: Array, default: () => [] },
    defaultWeek: { type: String, default: '' },
    success: { type: String, default: null },
});

const openForm = useForm({ subject_user_id: '', week_start_date: props.defaultWeek });

function go(url) {
    router.visit(url);
}

function openReview() {
    openForm.post('/revues-hebdomadaires', { preserveScroll: true });
}

async function focusOpenForm() {
    await nextTick();
    document.getElementById('weekly-review-subject')?.focus();
}
</script>

<template>
    <Head title="Revues hebdomadaires" />
    <AppLayout title="Revues hebdomadaires" active-navigation="weekly-reviews">
        <div class="grid min-w-0 gap-5">
            <header>
                <h1 class="text-screen-title">Revues hebdomadaires</h1>
                <p class="text-ink-secondary">
                    Les faits de la semaine sont rassemblés automatiquement. Chaque revue se conclut par
                    la validation des deux parties.
                </p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <form v-if="canOpen" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-2" @submit.prevent="openReview">
                <h2 class="text-section-title sm:col-span-2">Ouvrir une revue</h2>
                <label class="grid min-w-0 gap-1 font-semibold" for="weekly-review-subject">Personne concernée
                    <select id="weekly-review-subject" v-model="openForm.subject_user_id" required class="touch-target min-w-0 rounded-lg border border-separator px-3">
                        <option value="">Choisir</option>
                        <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                    </select>
                </label>
                <label class="grid min-w-0 gap-1 font-semibold" for="weekly-review-week">Semaine (un jour de la semaine)
                    <input id="weekly-review-week" v-model="openForm.week_start_date" type="date" required class="touch-target min-w-0 rounded-lg border border-separator px-3" />
                </label>
                <p class="text-sm text-ink-secondary sm:col-span-2">La revue porte sur la semaine du lundi au dimanche contenant ce jour ; elle est prévue le vendredi.</p>
                <ul v-if="Object.keys(openForm.errors).length" class="grid gap-1 sm:col-span-2" role="alert">
                    <li v-for="(message, key) in openForm.errors" :key="key" class="text-danger">{{ message }}</li>
                </ul>
                <AppButton type="submit" variant="principal" class="sm:col-span-2" :disabled="openForm.processing">Ouvrir la revue</AppButton>
            </form>

            <!-- Chaque revue est une carte : lisible à 320 px, sans tableau à faire défiler. -->
            <ul v-if="reviews.data.length" class="grid min-w-0 gap-3">
                <li v-for="review in reviews.data" :key="review.id">
                    <button
                        type="button"
                        class="touch-target grid w-full min-w-0 gap-1 rounded-xl border border-separator bg-surface p-4 text-left"
                        @click="go(review.url)"
                    >
                        <span class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-card-title">{{ review.subject }}</span>
                            <!-- L'état est porté par le libellé, jamais par la seule couleur. -->
                            <span class="text-sm font-semibold text-ink-secondary">{{ review.state_label }}</span>
                        </span>
                        <span class="text-ink-secondary">Semaine du {{ review.week_start_date }}</span>
                        <span class="text-ink-secondary">Tenue le {{ review.scheduled_on }}</span>
                        <span class="text-ink-secondary">Conduite par {{ review.reviewer }}</span>
                    </button>
                </li>
            </ul>

            <EmptyState
                v-else
                title="Aucune revue pour l’instant"
                reason="Aucune revue hebdomadaire ne vous concerne encore, ni comme personne évaluée, ni comme responsable."
                :action-label="canOpen ? 'Ouvrir une revue' : ''"
                @action="focusOpenForm"
            />
        </div>
    </AppLayout>
</template>
