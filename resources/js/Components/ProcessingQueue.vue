<script setup>
import { computed, ref } from 'vue';
import AppButton from './AppButton.vue';
import LoadingSkeleton from './LoadingSkeleton.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    state: { type: String, default: 'pleine' },
    sortBy: { type: String, default: 'ancienneté' },
    id: { type: String, default: 'processing-queue' },
    label: { type: String, default: 'File de traitement' },
});
const emit = defineEmits(['decide', 'retry']);
const activeIndex = ref(0);
const allowedSort = computed(() => ['ancienneté', 'nom'].includes(props.sortBy) ? props.sortBy : 'ancienneté');

function decide(item) {
    emit('decide', item);
    if (props.state !== 'erreur') {
        activeIndex.value = Math.min(activeIndex.value + 1, props.items.length - 1);
    }
}
</script>

<template>
    <section class="grid gap-3" :aria-labelledby="`${id}-title`">
        <div class="flex items-center justify-between gap-3"><h2 :id="`${id}-title`" class="text-section-title">{{ label }}</h2><span v-if="items.length" class="text-sm font-semibold">{{ activeIndex + 1 }} sur {{ items.length }}</span></div>
        <p class="text-sm text-ink-muted">Tri par {{ allowedSort }}</p>
        <LoadingSkeleton v-if="state === 'chargement'" shape="cards" force-state="skeleton" />
        <p v-else-if="state === 'vide'" class="rounded-xl border border-success bg-success-soft p-4 font-semibold text-success">✓ Tous les rapports sont traités</p>
        <article v-for="(item, index) in items" v-else :key="item.id" class="rounded-xl border border-separator bg-surface p-4">
            <button type="button" class="touch-target flex w-full items-center justify-between gap-3 text-left font-semibold" :aria-expanded="index === activeIndex" @click="activeIndex = index"><span>{{ item.name }}</span><span aria-hidden="true">{{ index === activeIndex ? '−' : '+' }}</span></button>
            <div v-if="index === activeIndex" class="grid gap-3 border-t border-separator pt-3"><p>{{ item.description }}</p><p v-if="state === 'erreur'" class="font-semibold text-danger">⚠ La décision n'a pas abouti. Cet élément reste dans la file.</p><div class="flex flex-wrap gap-2"><AppButton variant="secondaire" :busy="state === 'en cours'" busy-label="Décision en cours" @click="decide(item)">Traiter ce rapport</AppButton><AppButton v-if="state === 'erreur'" variant="discret" @click="$emit('retry', item)">Réessayer</AppButton></div></div>
        </article>
    </section>
</template>
