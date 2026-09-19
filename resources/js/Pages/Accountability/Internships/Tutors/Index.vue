<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import EmptyState from '../../../../Components/EmptyState.vue';

defineProps({
    tutors: { type: Array, required: true },
    limit: { type: Number, required: true },
});
</script>

<template>
    <Head title="Charge des tuteurs" />
    <AppLayout title="Charge des tuteurs" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <header>
                <h1 class="text-screen-title">Charge des tuteurs</h1>
                <p class="text-ink-secondary">
                    Chaque tuteur encadre au plus {{ limit }} stagiaires actifs. Un stage terminé libère une place.
                </p>
            </header>

            <ul v-if="tutors.length" class="grid min-w-0 gap-3">
                <li
                    v-for="tutor in tutors"
                    :key="tutor.id"
                    class="grid min-w-0 gap-1 rounded-xl border bg-surface p-4"
                    :class="tutor.at_limit ? 'border-warning bg-warning-soft' : 'border-separator'"
                >
                    <span class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-card-title">{{ tutor.name }}</span>
                        <!-- L'atteinte de la limite est portée par un libellé, jamais par la
                             seule couleur : le glyphe et le texte restent lisibles sans elle. -->
                        <span v-if="tutor.at_limit" class="text-sm font-semibold text-warning">
                            <span aria-hidden="true">⚠</span> {{ tutor.limit_label }}
                        </span>
                    </span>
                    <span class="text-ink-secondary">{{ tutor.load_label }}</span>
                    <span class="text-ink-secondary">
                        {{ tutor.at_limit ? 'Aucune place disponible.' : `${tutor.remaining} place(s) disponible(s).` }}
                    </span>
                </li>
            </ul>

            <EmptyState
                v-else
                title="Aucun tuteur"
                reason="Aucun compte ne peut encadrer de stagiaire pour l’instant."
            />
        </div>
    </AppLayout>
</template>
