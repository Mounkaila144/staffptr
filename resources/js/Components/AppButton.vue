<script setup>
const props = defineProps({
    variant: { type: String, default: 'secondaire' },
    busy: { type: Boolean, default: false },
    busyLabel: { type: String, default: 'Traitement en cours' },
    type: { type: String, default: 'button' },
});

const variants = {
    principal: 'min-h-12 w-full bg-primary text-white md:w-auto',
    secondaire: 'border border-primary bg-surface text-primary',
    discret: 'bg-transparent text-primary underline-offset-4 hover:underline',
    destructeur: 'border border-danger bg-surface text-danger',
};
</script>

<template>
    <button :type="type" :disabled="busy || $attrs.disabled" class="touch-target grid items-center justify-items-center rounded-lg px-4 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-70" :class="variants[variant]" :aria-busy="busy">
        <span class="col-start-1 row-start-1" :class="busy ? 'invisible' : ''"><slot /></span>
        <span class="col-start-1 row-start-1" :class="busy ? '' : 'invisible'" aria-live="polite">{{ busyLabel }}</span>
    </button>
</template>
