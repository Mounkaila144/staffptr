<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';

defineProps({
    reviews: { type: Object, required: true },
    canOpen: { type: Boolean, default: false },
    success: { type: String, default: null },
});

function go(url) {
    router.visit(url);
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
            />
        </div>
    </AppLayout>
</template>
