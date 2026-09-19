<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import EmptyState from '../../../Components/EmptyState.vue';

defineProps({
    internships: { type: Object, required: true },
    success: { type: String, default: null },
});
</script>

<template>
    <Head title="Stages" />
    <AppLayout title="Stages" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <header>
                <h1 class="text-screen-title">Stages</h1>
                <p class="text-ink-secondary">Chaque dossier réunit le plan de stage, les évaluations et les checklists.</p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <ul v-if="internships.data.length" class="grid min-w-0 gap-3">
                <li v-for="internship in internships.data" :key="internship.id">
                    <button
                        type="button"
                        class="touch-target grid w-full min-w-0 gap-1 rounded-xl border border-separator bg-surface p-4 text-left"
                        @click="router.visit(internship.url)"
                    >
                        <span class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-card-title">{{ internship.intern }}</span>
                            <span class="text-sm font-semibold text-ink-secondary">{{ internship.state_label }}</span>
                        </span>
                        <span class="text-ink-secondary">Tuteur : {{ internship.tutor }}</span>
                        <span class="text-ink-secondary">Depuis le {{ internship.start_date }}</span>
                    </button>
                </li>
            </ul>

            <EmptyState
                v-else
                title="Aucun stage"
                reason="Aucun dossier de stage ne vous concerne pour l’instant."
            />
        </div>
    </AppLayout>
</template>
