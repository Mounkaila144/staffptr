<script setup>
import AppIcon from './AppIcon.vue';

defineProps({
    block: { type: Object, required: true },
    // Identifiant stable pour les tests et pour l'ancrage `aria-labelledby`.
    blockKey: { type: String, required: true },
});

// L'icône rappelle la nature du bloc ; elle est décorative et doublée de son titre.
const icons = {
    approval_queue: 'approve', members_without_objective: 'team', daily_reports: 'report',
    objectives_by_state: 'target', late_projects: 'project', interns_by_tutor: 'intern',
    month_collections: 'wallet', month_charges: 'receipt', available_balance: 'coins',
    receivables: 'clock', reserve: 'shield', account_balances: 'wallet', pending_expenses: 'expense',
    overdue_receivables: 'clock', reconciliation_gaps: 'scale', budget_versus_actual: 'chart',
    share_commitments: 'pie',
};
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
        class="grid min-w-0 content-start gap-3 rounded-xl border border-separator bg-surface p-5"
        :aria-labelledby="`block-${blockKey}-title`"
        :data-testid="`dashboard-block-${blockKey}`"
    >
        <div class="flex min-w-0 items-start justify-between gap-3">
            <h3 :id="`block-${blockKey}-title`" class="min-w-0 break-words text-sm font-semibold text-ink-secondary">{{ block.title }}</h3>
            <slot name="badge" />
            <span v-if="!$slots.badge && icons[blockKey]" class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary">
                <AppIcon :name="icons[blockKey]" :size="18" />
            </span>
        </div>

        <slot />

        <p v-if="block.note" class="text-sm text-ink-secondary">{{ block.note }}</p>

        <a
            v-if="block.url"
            :href="block.url"
            class="touch-target inline-flex w-fit items-center text-sm font-semibold text-primary underline-offset-4 hover:underline"
        >
            Voir le détail<span class="sr-only"> de « {{ block.title }} »</span>
        </a>
    </section>
</template>
