<script setup>
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';

defineProps({
    tone: { type: String, default: 'neutral' },
    title: { type: String, required: true },
    reason: { type: String, required: true },
    actionLabel: { type: String, default: '' },
    // Une action qui mène à un autre écran est un lien, pas un événement sans destinataire.
    actionHref: { type: String, default: '' },
});
defineEmits(['action']);
</script>

<template>
    <section class="grid justify-items-center gap-2 rounded-xl border border-dashed px-5 py-8 text-center" :class="tone === 'positive' ? 'border-success/40 bg-success-soft' : tone === 'filter' ? 'border-warning/40 bg-warning-soft' : 'border-separator-strong bg-surface'">
        <span class="mb-1 grid size-12 place-items-center rounded-full" :class="tone === 'positive' ? 'bg-surface text-success' : tone === 'filter' ? 'bg-surface text-warning' : 'bg-neutral-soft text-ink-muted'">
            <AppIcon :name="tone === 'positive' ? 'approve' : tone === 'filter' ? 'search' : 'inbox'" :size="24" />
        </span>
        <h3 class="text-card-title">{{ title }}</h3>
        <p class="max-w-md text-sm text-ink-secondary">{{ reason }}</p>
        <AppButton v-if="actionHref && actionLabel" variant="secondaire" :href="actionHref">{{ actionLabel }}</AppButton>
        <AppButton v-else-if="actionLabel || tone === 'filter'" variant="secondaire" @click="$emit('action')">{{ tone === 'filter' ? 'Réinitialiser les filtres' : actionLabel }}</AppButton>
    </section>
</template>
