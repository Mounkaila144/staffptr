<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    forceState: { type: String, default: '' },
    shape: { type: String, default: 'cards' },
});
const state = ref(props.forceState || 'waiting');
const timers = [];

onMounted(() => {
    if (props.forceState) return;
    timers.push(window.setTimeout(() => { state.value = 'skeleton'; }, 300));
    timers.push(window.setTimeout(() => { state.value = 'slow'; }, 3000));
});
onBeforeUnmount(() => timers.forEach(window.clearTimeout));
</script>

<template>
    <div v-if="state !== 'waiting'" class="grid gap-3" role="status" aria-live="polite">
        <span class="sr-only">Chargement en cours</span>
        <template v-if="shape === 'cards'"><div v-for="index in 3" :key="index" class="h-24 animate-pulse rounded-xl bg-neutral-soft"></div></template>
        <template v-else><div class="h-4 w-2/3 animate-pulse rounded bg-neutral-soft"></div><div class="h-12 animate-pulse rounded bg-neutral-soft"></div></template>
        <p v-if="state === 'slow'" class="text-sm text-ink-secondary">Chargement en cours. La connexion semble lente.</p>
    </div>
</template>
