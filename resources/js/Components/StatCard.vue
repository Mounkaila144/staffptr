<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    icon: { type: String, default: '' },
    href: { type: String, default: '' },
    // Le ton colore l'icône seulement : le chiffre garde l'encre de texte (cahier § 5.4), et
    // l'état éventuel est toujours dit en toutes lettres dans `hint`.
    tone: { type: String, default: 'primary' },
});

const tones = {
    primary: 'bg-primary-soft text-primary',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    neutral: 'bg-neutral-soft text-neutral',
};
</script>

<template>
    <component
        :is="href ? 'a' : 'section'"
        :href="href || undefined"
        class="group grid min-w-0 content-start gap-3 rounded-xl border border-separator bg-surface p-5 transition-colors"
        :class="href ? 'hover:border-primary/40' : ''"
    >
        <div class="flex min-w-0 items-center justify-between gap-3">
            <p class="min-w-0 truncate text-sm font-medium text-ink-secondary">{{ label }}</p>
            <span v-if="icon" class="grid size-9 shrink-0 place-items-center rounded-lg" :class="tones[tone] ?? tones.primary">
                <AppIcon :name="icon" :size="18" />
            </span>
        </div>
        <p class="break-words text-tile text-ink">{{ value }}</p>
        <p v-if="hint" class="text-sm text-ink-muted">{{ hint }}</p>
        <slot />
        <p v-if="href" class="text-sm font-semibold text-primary group-hover:underline">Voir le détail</p>
    </component>
</template>
