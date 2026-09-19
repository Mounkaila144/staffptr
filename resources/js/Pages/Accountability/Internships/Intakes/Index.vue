<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import EmptyState from '../../../../Components/EmptyState.vue';

defineProps({
    forms: { type: Object, required: true },
    canCreate: { type: Boolean, default: false },
    success: { type: String, default: null },
});
</script>

<template>
    <Head title="Fiches d’entrée" />
    <AppLayout title="Fiches d’entrée" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <header>
                <h1 class="text-screen-title">Fiches d’entrée</h1>
                <p class="text-ink-secondary">
                    Une fiche précise le besoin réel, la mission, le tuteur et les trois résultats attendus.
                </p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <ul v-if="forms.data.length" class="grid min-w-0 gap-3">
                <li v-for="form in forms.data" :key="form.id">
                    <button
                        type="button"
                        class="touch-target grid w-full min-w-0 gap-1 rounded-xl border border-separator bg-surface p-4 text-left"
                        @click="router.visit(`/stages/fiches-entree/${form.id}`)"
                    >
                        <span class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-card-title">{{ form.candidate }}</span>
                            <span class="text-sm font-semibold text-ink-secondary">{{ form.state_label }}</span>
                        </span>
                        <span class="text-ink-secondary">Tuteur : {{ form.tutor || 'à désigner' }}</span>
                        <span class="text-ink-secondary">{{ form.duration_weeks }} semaines — {{ form.outcomes.length }} résultats attendus</span>
                    </button>
                </li>
            </ul>

            <EmptyState
                v-else
                title="Aucune fiche d’entrée"
                reason="Aucune fiche d’entrée ne vous concerne pour l’instant."
                :action-label="canCreate ? 'Rédiger une fiche' : ''"
            />
        </div>
    </AppLayout>
</template>
