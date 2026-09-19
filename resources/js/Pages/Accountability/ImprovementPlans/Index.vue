<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';

defineProps({
    plans: { type: Object, required: true },
    success: { type: String, default: null },
});
</script>

<template>
    <Head title="Plans d’accompagnement" />
    <AppLayout title="Plans d’accompagnement" active-navigation="improvement-plans">
        <div class="grid min-w-0 gap-5">
            <header>
                <h1 class="text-screen-title">Plans d’accompagnement</h1>
                <p class="text-ink-secondary">
                    Un plan précise l’aide fournie, les actions convenues et le résultat constaté à la fin.
                </p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <ul v-if="plans.data.length" class="grid min-w-0 gap-3">
                <li v-for="plan in plans.data" :key="plan.id">
                    <button
                        type="button"
                        class="touch-target grid w-full min-w-0 gap-1 rounded-xl border border-separator bg-surface p-4 text-left"
                        @click="router.visit(`/plans-accompagnement/${plan.id}`)"
                    >
                        <span class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-card-title">{{ plan.subject }}</span>
                            <span class="text-sm font-semibold text-ink-secondary">{{ plan.state_label }}</span>
                        </span>
                        <span class="text-ink-secondary">Du {{ plan.start_date }} au {{ plan.end_date }} — {{ plan.duration_days }} jours</span>
                        <span class="text-ink-secondary">{{ plan.actions.length }} action(s) convenue(s)</span>
                    </button>
                </li>
            </ul>

            <EmptyState
                v-else
                title="Aucun plan d’accompagnement"
                reason="Aucun plan ne vous concerne pour l’instant. Un plan se met en place depuis une revue hebdomadaire."
            />
        </div>
    </AppLayout>
</template>
