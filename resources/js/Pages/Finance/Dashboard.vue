<script setup>
import { Deferred, Head } from '@inertiajs/vue3';
import AlertLevelCard from '../../Components/AlertLevelCard.vue';
import DashboardBlock from '../../Components/DashboardBlock.vue';
import EmptyState from '../../Components/EmptyState.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    alert: { type: Object, required: true },
    blocks: { type: Object, default: null },
});
</script>

<template>
    <Head title="Tableau de bord financier" />
    <!--
        Une seule colonne en dessous de 640 px : les cartes s'empilent, rien ne déborde à 320 px
        et il n'y a aucun défilement horizontal (AC 24, SOC-09). `min-w-0` sur chaque conteneur
        empêche un montant long de forcer la largeur de la grille.
    -->
    <AppLayout title="Tableau de bord financier" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Tableau de bord financier</h1>
                <p class="text-ink-secondary">Situation consolidée de la trésorerie, des engagements et des écarts.</p>
            </header>

            <AlertLevelCard :alert="alert" />

            <!-- Les agrégats arrivent après le premier rendu utile : squelette entre 300 ms et 3 s. -->
            <Deferred data="blocks">
                <template #fallback>
                    <LoadingSkeleton shape="cards" />
                </template>

                <EmptyState
                    v-if="!blocks || Object.keys(blocks).length === 0"
                    title="Aucun indicateur financier ne vous est accessible."
                    reason="Votre rôle ne donne accès à aucun des blocs de ce tableau de bord."
                />

                <div v-else class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <DashboardBlock v-if="blocks.account_balances" :block="blocks.account_balances" block-key="account_balances">
                        <p class="text-card-title">{{ blocks.account_balances.total_label }}</p>
                        <ul v-if="blocks.account_balances.items.length" class="grid min-w-0 gap-2">
                            <li v-for="account in blocks.account_balances.items" :key="account.id" class="flex min-w-0 flex-wrap justify-between gap-2 rounded-lg bg-neutral-soft p-2">
                                <span class="break-words">{{ account.label }} · {{ account.state_label }}</span>
                                <span class="font-semibold">
                                    <span v-if="account.is_negative">−</span>{{ account.amount_label }}
                                </span>
                            </li>
                        </ul>
                        <p v-else>{{ blocks.account_balances.empty_message }}</p>
                    </DashboardBlock>

                    <DashboardBlock v-if="blocks.pending_expenses" :block="blocks.pending_expenses" block-key="pending_expenses">
                        <p class="text-card-title">{{ blocks.pending_expenses.count }} en attente</p>
                        <p v-if="blocks.pending_expenses.count > 0">{{ blocks.pending_expenses.amount_label }}</p>
                        <p v-else>{{ blocks.pending_expenses.empty_message }}</p>
                    </DashboardBlock>

                    <DashboardBlock v-if="blocks.month_collections" :block="blocks.month_collections" block-key="month_collections">
                        <p class="text-card-title">{{ blocks.month_collections.amount_label }}</p>
                        <p v-if="blocks.month_collections.count > 0">
                            {{ blocks.month_collections.count }} encaissement{{ blocks.month_collections.count > 1 ? 's' : '' }} · {{ blocks.month_collections.month_label }}
                        </p>
                        <p v-else>{{ blocks.month_collections.empty_message }}</p>
                    </DashboardBlock>

                    <DashboardBlock v-if="blocks.overdue_receivables" :block="blocks.overdue_receivables" block-key="overdue_receivables">
                        <p class="text-card-title">{{ blocks.overdue_receivables.amount_label }}</p>
                        <ul v-if="blocks.overdue_receivables.items.length" class="grid min-w-0 gap-2">
                            <li v-for="invoice in blocks.overdue_receivables.items" :key="invoice.id" class="grid min-w-0 gap-1 rounded-lg bg-neutral-soft p-2">
                                <span class="break-words font-semibold">{{ invoice.number }} · {{ invoice.client }}</span>
                                <span class="text-sm">Échue le {{ invoice.due_on }} · {{ invoice.amount_label }}</span>
                            </li>
                        </ul>
                        <p v-else>{{ blocks.overdue_receivables.empty_message }}</p>
                    </DashboardBlock>

                    <DashboardBlock v-if="blocks.reconciliation_gaps" :block="blocks.reconciliation_gaps" block-key="reconciliation_gaps">
                        <p class="text-card-title">{{ blocks.reconciliation_gaps.amount_label }}</p>
                        <p v-if="blocks.reconciliation_gaps.count > 0">
                            {{ blocks.reconciliation_gaps.count }} rapprochement{{ blocks.reconciliation_gaps.count > 1 ? 's' : '' }} avec écart
                        </p>
                        <p v-else>{{ blocks.reconciliation_gaps.empty_message }}</p>
                    </DashboardBlock>

                    <DashboardBlock v-if="blocks.budget_versus_actual" :block="blocks.budget_versus_actual" block-key="budget_versus_actual">
                        <!-- Le dépassement est dit en toutes lettres, pas seulement coloré (SOC-10). -->
                        <p class="text-card-title">{{ blocks.budget_versus_actual.status_label }}</p>
                        <dl class="grid min-w-0 gap-1">
                            <div class="flex flex-wrap justify-between gap-2"><dt>Budget</dt><dd class="font-semibold">{{ blocks.budget_versus_actual.budget_amount_label }}</dd></div>
                            <div class="flex flex-wrap justify-between gap-2"><dt>Réalisé</dt><dd class="font-semibold">{{ blocks.budget_versus_actual.actual_amount_label }}</dd></div>
                        </dl>
                    </DashboardBlock>

                    <DashboardBlock v-if="blocks.reserve" :block="blocks.reserve" block-key="reserve">
                        <p class="text-card-title">{{ blocks.reserve.amount_label }}</p>
                    </DashboardBlock>

                    <DashboardBlock v-if="blocks.share_commitments" :block="blocks.share_commitments" block-key="share_commitments">
                        <p class="text-card-title">{{ blocks.share_commitments.amount_label }}</p>
                        <p v-if="blocks.share_commitments.count > 0">
                            {{ blocks.share_commitments.count }} part{{ blocks.share_commitments.count > 1 ? 's' : '' }} sur contrats en cours
                        </p>
                        <p v-else>{{ blocks.share_commitments.empty_message }}</p>
                    </DashboardBlock>
                </div>
            </Deferred>
        </div>
    </AppLayout>
</template>
