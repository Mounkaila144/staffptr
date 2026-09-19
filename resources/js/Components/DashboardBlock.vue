<script setup>
defineProps({
    block: { type: Object, required: true },
    // Identifiant stable pour les tests et pour l'ancrage `aria-labelledby`.
    blockKey: { type: String, required: true },
});
</script>

<template>
    <!--
        Un bloc n'est rendu que si le serveur l'a envoyé. Un bloc non autorisé n'arrive jamais
        jusqu'ici, même vide (AC 23, AC 43, FR172) : il n'y a donc volontairement aucun `v-if`
        de permission dans ce composant.

        La carte entière est cliquable vers la liste détaillée (AC 21), avec une cible tactile
        d'au moins 44 px et un libellé explicite plutôt qu'une flèche seule.
    -->
    <section
        class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4"
        :aria-labelledby="`block-${blockKey}-title`"
        :data-testid="`dashboard-block-${blockKey}`"
    >
        <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
            <h3 :id="`block-${blockKey}-title`" class="break-words text-card-title">{{ block.title }}</h3>
            <slot name="badge" />
        </div>

        <slot />

        <p v-if="block.note" class="text-sm text-ink-secondary">{{ block.note }}</p>

        <a
            v-if="block.url"
            :href="block.url"
            class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline"
        >
            Voir le détail<span class="sr-only"> de « {{ block.title }} »</span>
        </a>
    </section>
</template>
