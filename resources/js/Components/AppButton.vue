<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'secondaire' },
    // Avec une adresse, le bouton devient un lien : un `<button href>` ne mène nulle part.
    href: { type: String, default: '' },
    busy: { type: Boolean, default: false },
    busyLabel: { type: String, default: 'Traitement en cours' },
    type: { type: String, default: 'button' },
});

const variants = {
    principal: 'min-h-12 w-full bg-primary text-white shadow-sm hover:bg-primary-strong md:min-h-11 md:w-auto',
    secondaire: 'border border-separator-strong bg-surface text-primary shadow-sm hover:border-primary hover:bg-primary-soft',
    discret: 'bg-transparent text-primary underline-offset-4 hover:underline',
    destructeur: 'border border-danger/40 bg-surface text-danger shadow-sm hover:border-danger hover:bg-danger-soft',
};
// Des pages écrivent `danger` : sans cet alias, leur bouton s'affichait sans aucun style.
variants.danger = variants.destructeur;

</script>

<template>
    <Link v-if="href" :href="href" class="touch-target inline-grid items-center justify-items-center rounded-lg px-4 py-2 text-[0.9375rem] font-semibold transition-colors duration-150" :class="variants[variant]">
        <slot />
    </Link>
    <button v-else :type="type" :disabled="busy || $attrs.disabled" class="touch-target grid items-center justify-items-center rounded-lg px-4 py-2 text-[0.9375rem] font-semibold transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-60" :class="variants[variant]" :aria-busy="busy">
        <span class="col-start-1 row-start-1" :class="busy ? 'invisible' : ''"><slot /></span>
        <span class="col-start-1 row-start-1" :class="busy ? '' : 'invisible'" aria-live="polite">{{ busyLabel }}</span>
    </button>
</template>
