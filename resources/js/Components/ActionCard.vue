<script setup>
import StatusBadge from './StatusBadge.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    variant: { type: String, default: 'standard' },
    state: { type: String, default: 'attendue' },
    permitted: { type: Boolean, default: true },
});
</script>

<template>
    <article v-if="permitted" class="grid gap-3 rounded-xl border bg-surface p-4" :class="variant === 'urgente' ? 'border-l-4 border-l-danger' : variant === 'compacte' ? 'py-3' : 'border-separator'">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="text-card-title">{{ title }}</h3>
            <StatusBadge v-if="state === 'faite'" status="validé" />
            <StatusBadge v-else-if="state === 'non applicable'" status="bloqué" variant="avec contexte" context="Sans action" />
        </div>
        <p class="text-ink-secondary">{{ description }}</p>
        <div v-if="state === 'attendue'"><slot /></div>
        <p v-else-if="state === 'faite'" class="font-semibold text-success">✓ Action terminée aujourd'hui</p>
    </article>
</template>
