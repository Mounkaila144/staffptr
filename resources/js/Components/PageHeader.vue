<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
});
</script>

<template>
    <header class="flex min-w-0 flex-wrap items-end justify-between gap-4">
        <div class="grid min-w-0 gap-1">
            <p v-if="eyebrow" class="text-sm font-medium text-ink-muted">{{ eyebrow }}</p>
            <h1 class="break-words text-page-title">{{ title }}</h1>
            <p v-if="description" class="max-w-2xl text-ink-secondary">{{ description }}</p>
            <slot name="meta" />
        </div>
        <div v-if="$slots.actions" class="flex w-full flex-wrap gap-2 sm:w-auto">
            <slot name="actions" />
        </div>
    </header>
</template>
