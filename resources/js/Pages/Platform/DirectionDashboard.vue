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
    <Head title="Tableau de bord direction" />
    <AppLayout title="Tableau de bord direction" active-navigation="home">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Tableau de bord direction</h1>
                <p class="text-ink-secondary">Vue consolidée des équipes, du travail et de la situation financière.</p>
            </header>

            <Deferred data="blocks">
                <template #fallback>
                    <LoadingSkeleton shape="cards" />
                </template>

                <div class="grid min-w-0 gap-4">
                    <!--
                        AC 26, FR167 : « En attente de mon approbation » occupe la première
                        position, hors de la grille des autres blocs pour qu'aucune règle de
                        placement responsive ne puisse le faire descendre.
                    -->
                    <DashboardBlock
                        v-if="blocks && blocks.approval_queue"
                        :block="{ ...blocks.approval_queue, title: 'En attente de mon approbation' }"
                        block-key="approval_queue"
                    >
                        <template #badge>
                            <span v-if="blocks.approval_queue.count > 0" class="rounded-full border border-primary px-3 py-1 font-bold text-primary">
                                {{ blocks.approval_queue.count }} à traiter
                            </span>
                        </template>
                        <p v-if="blocks.approval_queue.count > 0" class="text-card-title">
                            {{ blocks.approval_queue.count }} dépense{{ blocks.approval_queue.count > 1 ? 's' : '' }} · {{ blocks.approval_queue.amount_label }}
                        </p>
                        <p v-else>{{ blocks.approval_queue.empty_message }}</p>
                    </DashboardBlock>

                    <AlertLevelCard :alert="alert" />

                    <EmptyState
                        v-if="!blocks || Object.keys(blocks).length === 0"
                        title="Aucun bloc de ce tableau de bord ne vous est accessible."
                        reason="Votre rôle ne donne accès à aucun des indicateurs consolidés."
                    />

                    <div v-else class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <DashboardBlock v-if="blocks.members_without_objective" :block="blocks.members_without_objective" block-key="members_without_objective">
                            <p class="text-card-title">{{ blocks.members_without_objective.count }}</p>
                            <ul v-if="blocks.members_without_objective.items.length" class="grid min-w-0 gap-1">
                                <li v-for="member in blocks.members_without_objective.items" :key="member.id" class="break-words rounded-lg bg-neutral-soft p-2">
                                    {{ member.name }}
                                </li>
                            </ul>
                            <p v-else>{{ blocks.members_without_objective.empty_message }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.daily_reports" :block="blocks.daily_reports" block-key="daily_reports">
                            <template v-if="blocks.daily_reports.is_working_day">
                                <p class="text-card-title">{{ blocks.daily_reports.sent }} / {{ blocks.daily_reports.expected }} envoyés</p>
                                <p>{{ blocks.daily_reports.missing }} manquant{{ blocks.daily_reports.missing > 1 ? 's' : '' }}</p>
                            </template>
                            <!-- SOC-06 : le vide par absence d'attente ne se confond pas avec le vide par absence de donnée. -->
                            <p v-else>{{ blocks.daily_reports.empty_message }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.objectives_by_state" :block="blocks.objectives_by_state" block-key="objectives_by_state">
                            <p class="text-card-title">{{ blocks.objectives_by_state.total }} objectifs</p>
                            <ul v-if="blocks.objectives_by_state.total > 0" class="grid min-w-0 gap-1">
                                <li v-for="group in blocks.objectives_by_state.items" :key="group.key" class="flex flex-wrap justify-between gap-2 rounded-lg bg-neutral-soft p-2">
                                    <span>{{ group.label }}</span><span class="font-semibold">{{ group.count }}</span>
                                </li>
                            </ul>
                            <p v-else>{{ blocks.objectives_by_state.empty_message }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.late_projects" :block="blocks.late_projects" block-key="late_projects">
                            <p class="text-card-title">{{ blocks.late_projects.count }}</p>
                            <ul v-if="blocks.late_projects.items.length" class="grid min-w-0 gap-1">
                                <li v-for="project in blocks.late_projects.items" :key="project.id" class="grid min-w-0 rounded-lg bg-neutral-soft p-2">
                                    <span class="break-words font-semibold">{{ project.name }}</span>
                                    <span class="text-sm">{{ project.status_label }} · fin prévue le {{ project.end_date }}</span>
                                </li>
                            </ul>
                            <p v-else>{{ blocks.late_projects.empty_message }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.interns_by_tutor" :block="blocks.interns_by_tutor" block-key="interns_by_tutor">
                            <!-- AC 27, FR170 : la limite atteinte est portée par un libellé, pas par la couleur seule. -->
                            <p class="text-card-title">{{ blocks.interns_by_tutor.at_limit_label }}</p>
                            <ul v-if="blocks.interns_by_tutor.items.length" class="grid min-w-0 gap-1">
                                <li
                                    v-for="tutor in blocks.interns_by_tutor.items"
                                    :key="tutor.id"
                                    class="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-lg p-2"
                                    :class="tutor.at_limit ? 'border border-warning bg-warning-soft' : 'bg-neutral-soft'"
                                    :data-at-limit="tutor.at_limit ? 'true' : 'false'"
                                >
                                    <span class="break-words">{{ tutor.name }}</span>
                                    <span class="font-semibold">
                                        <span v-if="tutor.at_limit" aria-hidden="true">⚠ </span>{{ tutor.load_label }}
                                        <span v-if="tutor.limit_label"> · {{ tutor.limit_label }}</span>
                                    </span>
                                </li>
                            </ul>
                            <p v-else>{{ blocks.interns_by_tutor.empty_message }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.month_collections" :block="blocks.month_collections" block-key="month_collections">
                            <p class="text-card-title">{{ blocks.month_collections.amount_label }}</p>
                            <p>{{ blocks.month_collections.month_label }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.month_charges" :block="blocks.month_charges" block-key="month_charges">
                            <p class="text-card-title">{{ blocks.month_charges.amount_label }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.available_balance" :block="blocks.available_balance" block-key="available_balance">
                            <p class="text-card-title">
                                <span v-if="blocks.available_balance.is_negative">−</span>{{ blocks.available_balance.amount_label }}
                            </p>
                            <p v-if="blocks.available_balance.is_negative">Solde négatif.</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.receivables" :block="blocks.receivables" block-key="receivables">
                            <p class="text-card-title">{{ blocks.receivables.amount_label }}</p>
                            <p v-if="blocks.receivables.count > 0">
                                {{ blocks.receivables.count }} facture{{ blocks.receivables.count > 1 ? 's' : '' }} échue{{ blocks.receivables.count > 1 ? 's' : '' }}
                            </p>
                            <p v-else>{{ blocks.receivables.empty_message }}</p>
                        </DashboardBlock>

                        <DashboardBlock v-if="blocks.reserve" :block="blocks.reserve" block-key="reserve">
                            <p class="text-card-title">{{ blocks.reserve.amount_label }}</p>
                            <p>{{ blocks.reserve.covered_months_label }}</p>
                        </DashboardBlock>
                    </div>
                </div>
            </Deferred>
        </div>
    </AppLayout>
</template>
