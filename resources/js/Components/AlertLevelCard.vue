<script setup>
import { computed } from 'vue';

const props = defineProps({
    alert: { type: Object, required: true },
});

// Le niveau est toujours doublé d'un glyphe et d'un libellé : aucune information n'est portée
// par la couleur seule (AC 4, NFR31, SOC-10). Le glyphe reste lisible en niveaux de gris.
const tones = {
    vert: ['✓', 'border-success bg-success-soft text-success'],
    orange: ['▲', 'border-warning bg-warning-soft text-warning'],
    rouge: ['⚠', 'border-danger bg-danger-soft text-danger'],
};

const tone = computed(() => tones[props.alert.level] ?? tones.vert);
</script>

<template>
    <section
        class="grid min-w-0 gap-3 rounded-xl border-2 p-4"
        :class="tone[1]"
        aria-labelledby="alert-level-title"
        data-testid="alert-level-card"
        :data-alert-level="alert.level"
    >
        <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
            <h2 id="alert-level-title" class="break-words text-section-title">Niveau d'alerte financière</h2>
            <span class="inline-flex min-h-7 items-center gap-1.5 rounded-full border px-3 py-1 font-bold" :class="tone[1]">
                <span aria-hidden="true">{{ tone[0] }}</span>
                <span>{{ alert.level_label }}</span>
            </span>
        </div>

        <p class="text-ink-secondary">
            Mois de {{ alert.month_label }}<span v-if="alert.frozen"> — niveau figé à la clôture, il n'est plus recalculé</span>.
        </p>

        <!-- AC 6 : le calcul dit sa méthode et la date de ses données source. -->
        <details class="rounded-lg bg-surface/60 p-3">
            <summary class="touch-target inline-flex cursor-pointer items-center font-semibold">
                Méthode de calcul et date des données
            </summary>
            <p class="mt-2 break-words text-sm">{{ alert.method }}</p>
            <p class="mt-1 text-sm text-ink-secondary">Données arrêtées au {{ alert.source_date }}.</p>
        </details>
    </section>
</template>
