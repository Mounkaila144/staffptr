<script setup>
import AppButton from './AppButton.vue';

defineProps({
    tone: { type: String, default: 'neutral' },
    title: { type: String, required: true },
    reason: { type: String, required: true },
    actionLabel: { type: String, default: '' },
});
defineEmits(['action']);
</script>

<template>
    <section class="grid justify-items-center gap-3 rounded-xl border p-5 text-center" :class="tone === 'positive' ? 'border-success bg-success-soft' : tone === 'filter' ? 'border-warning bg-warning-soft' : 'border-separator bg-neutral-soft'">
        <span class="text-3xl text-neutral" aria-hidden="true">◇</span>
        <h3 class="text-card-title">{{ title }}</h3>
        <p class="text-ink-secondary">{{ reason }}</p>
        <AppButton v-if="actionLabel || tone === 'filter'" variant="secondaire" @click="$emit('action')">{{ tone === 'filter' ? 'Réinitialiser les filtres' : actionLabel }}</AppButton>
    </section>
</template>
