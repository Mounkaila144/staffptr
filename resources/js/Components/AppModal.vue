<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    // `lg` pour un formulaire à deux colonnes ; `md` suffit à une confirmation.
    size: { type: String, default: 'md' },
});
const emit = defineEmits(['update:open', 'close']);
const dialog = ref(null);
const titleId = `modal-${Math.random().toString(36).slice(2, 10)}`;

/*
 * `<dialog>` natif : le navigateur piège le focus, rend le reste de la page inerte, ferme sur
 * Échap et rend le focus au déclencheur. Aucune de ces garanties n'est réécrite à la main.
 */
watch(() => props.open, async (open) => {
    await nextTick();
    if (!dialog.value) {
        return;
    }
    if (open && !dialog.value.open) {
        dialog.value.showModal();
    } else if (!open && dialog.value.open) {
        dialog.value.close();
    }
}, { immediate: true });

function close() {
    emit('update:open', false);
    emit('close');
}

// Un clic sur le voile — et non dans le panneau — ferme la fenêtre, comme Échap.
function closeOnBackdrop(event) {
    if (event.target === dialog.value) {
        close();
    }
}

onBeforeUnmount(() => dialog.value?.open && dialog.value.close());
</script>

<template>
    <dialog
        ref="dialog"
        class="modal m-auto w-[calc(100%-2rem)] max-h-[calc(100dvh-2rem)] overflow-hidden rounded-2xl border border-separator bg-surface p-0 text-ink shadow-raised"
        :class="size === 'lg' ? 'max-w-2xl' : 'max-w-lg'"
        :aria-labelledby="titleId"
        @cancel.prevent="close"
        @click="closeOnBackdrop"
    >
        <div class="flex max-h-[calc(100dvh-2rem)] flex-col">
            <header class="flex items-start gap-4 border-b border-separator px-5 py-4 sm:px-6">
                <div class="grid min-w-0 flex-1 gap-1">
                    <h2 :id="titleId" class="text-section-title">{{ title }}</h2>
                    <p v-if="description" class="text-sm text-ink-secondary">{{ description }}</p>
                </div>
                <button type="button" class="touch-target -mr-2 -mt-1 inline-flex items-center justify-center rounded-lg text-ink-muted transition-colors hover:bg-neutral-soft hover:text-ink" aria-label="Fermer" @click="close">
                    <AppIcon name="close" :size="20" />
                </button>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                <slot />
            </div>
            <footer v-if="$slots.footer" class="flex flex-wrap justify-end gap-2 border-t border-separator bg-neutral-soft px-5 py-3 sm:px-6">
                <slot name="footer" />
            </footer>
        </div>
    </dialog>
</template>
