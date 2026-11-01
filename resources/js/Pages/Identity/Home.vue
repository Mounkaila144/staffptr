<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StatCard from '../../Components/StatCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    dailyReport: { type: Object, default: null },
    blockerBlock: { type: Object, default: null },
    approvalQueue: { type: Object, default: null },
    workBlocks: { type: Object, required: true },
    lastEvaluation: { type: Object, default: null },
});

// Chiffres clés : ils ne font que résumer les blocs envoyés par le serveur. Un bloc non autorisé
// n'arrive jamais, et son chiffre n'apparaît donc pas non plus.
const stats = computed(() => [
    props.workBlocks.todayTasks && { key: 'tasks', label: 'Tâches du jour', value: props.workBlocks.todayTasks.items.length, icon: 'tasks', href: props.workBlocks.todayTasks.url, hint: props.workBlocks.todayTasks.items.length ? 'À faire aujourd’hui' : 'Rien de prévu aujourd’hui' },
    props.workBlocks.objectives && { key: 'objectives', label: 'Objectifs du mois', value: props.workBlocks.objectives.items.length, icon: 'target', href: props.workBlocks.objectives.url, hint: 'Validés pour ce mois' },
    props.blockerBlock && { key: 'blockers', label: 'Blocages ouverts', value: props.blockerBlock.items.length, icon: 'blocker', tone: props.blockerBlock.items.length ? 'warning' : 'success', href: props.blockerBlock.url, hint: props.blockerBlock.items.length ? 'En attente de solution' : 'Aucun blocage' },
    props.workBlocks.notifications && { key: 'notifications', label: 'Notifications', value: props.workBlocks.notifications.count, icon: 'bell', tone: 'neutral', href: props.workBlocks.notifications.url, hint: props.workBlocks.notifications.count > 1 ? 'Non lues' : 'Non lue' },
].filter(Boolean));

// Seule la première lettre prend une majuscule : `capitalize` en CSS écrirait « Octobre ».
const todayLabel = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Africa/Niamey' }).format(new Date());
const today = todayLabel.charAt(0).toUpperCase() + todayLabel.slice(1);
</script>

<template>
    <Head title="Accueil" />
    <AppLayout title="Accueil" active-navigation="home">
        <div class="grid min-w-0 gap-6">
            <p class="text-sm font-medium text-ink-muted">{{ today }}</p>

            <section v-if="dailyReport && !dailyReport.after_approval" class="grid min-w-0 gap-3 rounded-xl border border-l-4 border-separator border-l-primary bg-surface p-4" aria-labelledby="daily-report-home-title" data-testid="daily-report-block">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <h1 id="daily-report-home-title" class="break-words text-screen-title">{{ dailyReport.title }}</h1>
                    <span class="rounded-full border border-primary px-3 py-1 font-semibold">{{ dailyReport.status }}</span>
                </div>
                <Link :href="dailyReport.action_url" class="touch-target inline-flex w-fit items-center font-semibold text-primary">{{ dailyReport.action_label }}</Link>
            </section>

            <section
                v-if="approvalQueue"
                class="grid min-w-0 gap-4 rounded-xl border border-l-4 border-separator border-l-primary bg-surface p-4 sm:p-6"
                aria-labelledby="approval-queue-title"
                data-testid="approval-queue-block"
            >
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <div class="grid min-w-0 gap-1">
                        <h1 id="approval-queue-title" class="break-words text-screen-title">En attente de mon approbation</h1>
                        <p v-if="approvalQueue.count > 0" class="text-ink-secondary">
                            {{ approvalQueue.count }} dépense{{ approvalQueue.count > 1 ? 's' : '' }} · plus ancienne : {{ approvalQueue.oldest_age_label }}
                        </p>
                    </div>
                    <span v-if="approvalQueue.count > 0" class="rounded-full border border-primary px-3 py-1 font-bold text-primary">
                        {{ approvalQueue.count }} à traiter
                    </span>
                </div>

                <template v-if="approvalQueue.count > 0">
                    <ul class="grid min-w-0 gap-3" aria-label="Dépenses en attente de votre approbation">
                        <li v-for="expense in approvalQueue.items" :key="expense.id" class="grid min-w-0 gap-2 rounded-lg border border-separator p-3">
                            <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                                <p class="break-words font-semibold">{{ expense.reason }}</p>
                                <span class="font-semibold text-primary">{{ expense.formatted_amount }}</span>
                            </div>
                            <p class="text-sm text-ink-secondary">Demandée par {{ expense.requester }} · {{ expense.age_label }}</p>
                            <Link :href="expense.decision_url" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">
                                Décider maintenant
                            </Link>
                        </li>
                    </ul>
                    <Link href="/depenses/approbations" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">
                        Voir toute la file
                    </Link>
                </template>

                <div v-else class="grid min-w-0 gap-3 rounded-lg border border-separator bg-neutral-soft p-4">
                    <p>{{ approvalQueue.empty_message }}</p>
                    <Link :href="approvalQueue.handled_url" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">
                        Voir les dépenses traitées
                    </Link>
                </div>
            </section>

            <section v-if="dailyReport && dailyReport.after_approval" class="grid min-w-0 gap-3 rounded-xl border border-l-4 border-separator border-l-primary bg-surface p-4" aria-labelledby="daily-report-direction-title" data-testid="daily-report-block">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <h2 id="daily-report-direction-title" class="break-words text-section-title">{{ dailyReport.title }}</h2>
                    <span class="rounded-full border border-primary px-3 py-1 font-semibold">{{ dailyReport.status }}</span>
                </div>
                <Link :href="dailyReport.action_url" class="touch-target inline-flex w-fit items-center font-semibold text-primary">{{ dailyReport.action_label }}</Link>
            </section>

            <section v-if="stats.length" class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Chiffres clés">
                <StatCard v-for="stat in stats" :key="stat.key" :label="stat.label" :value="stat.value" :icon="stat.icon" :tone="stat.tone ?? 'primary'" :hint="stat.hint" :href="stat.href" />
            </section>

            <div class="grid min-w-0 items-start gap-6 lg:grid-cols-2">
            <section v-if="workBlocks.objectives" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="monthly-objectives-title">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <h2 id="monthly-objectives-title" class="break-words text-section-title">{{ workBlocks.objectives.title }}</h2>
                    <Link :href="workBlocks.objectives.url" class="touch-target inline-flex items-center px-3 font-semibold text-primary">Voir les objectifs</Link>
                </div>
                <ul v-if="workBlocks.objectives.items.length" class="grid min-w-0 gap-3">
                    <li v-for="objective in workBlocks.objectives.items" :key="objective.id" class="grid min-w-0 gap-2 rounded-lg bg-neutral-soft p-3">
                        <div class="flex min-w-0 flex-wrap justify-between gap-2"><span class="break-words font-semibold">{{ objective.title }}</span><span class="text-sm text-ink-secondary">{{ objective.state_label }} — {{ objective.tone }}</span></div>
                        <div class="flex items-center gap-3"><progress :value="objective.progress" max="100" class="h-2 w-full" :aria-label="`Progression ${objective.progress} %`" /><span class="text-sm font-semibold">{{ objective.progress }} %</span></div>
                    </li>
                </ul>
                <p v-else>{{ workBlocks.objectives.empty_message }}</p>
            </section>

            <section v-if="workBlocks.todayTasks" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="today-tasks-title">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2"><h2 id="today-tasks-title" class="text-section-title">{{ workBlocks.todayTasks.title }}</h2><Link :href="workBlocks.todayTasks.url" class="touch-target inline-flex items-center px-3 font-semibold text-primary">Ouvrir la liste du jour</Link></div>
                <ul v-if="workBlocks.todayTasks.items.length" class="grid min-w-0 gap-2"><li v-for="task in workBlocks.todayTasks.items" :key="task.id" class="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-lg border border-separator p-3"><strong class="min-w-0 break-words">{{ task.title }}</strong><span class="text-sm text-ink-secondary">{{ task.status_label }} · {{ task.priority_label }}</span></li></ul>
                <p v-else>{{ workBlocks.todayTasks.empty_message }}</p>
            </section>

            <section v-if="workBlocks.deadlines" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="deadlines-title">
                <h2 id="deadlines-title" class="text-section-title">{{ workBlocks.deadlines.title }}</h2><ul v-if="workBlocks.deadlines.items.length" class="grid gap-2"><li v-for="item in workBlocks.deadlines.items" :key="`${item.title}-${item.date}`" class="flex min-w-0 flex-wrap justify-between gap-2 rounded-lg bg-neutral-soft p-3"><span class="break-words">{{ item.title }}</span><time>{{ item.date }}</time></li></ul><p v-else>{{ workBlocks.deadlines.empty_message }}</p>
            </section>

            <section v-if="blockerBlock" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="open-blockers-title" data-testid="open-blockers-block"><div class="flex flex-wrap items-center justify-between gap-2"><h2 id="open-blockers-title" class="text-section-title">{{ blockerBlock.title }}</h2><Link :href="blockerBlock.url" class="touch-target inline-flex items-center font-semibold text-primary">Voir les blocages</Link></div><p v-if="blockerBlock.items.length === 0">{{ blockerBlock.empty_message }}</p><ul v-else class="grid gap-2"><li v-for="blocker in blockerBlock.items" :key="blocker.id" class="rounded-lg bg-neutral-soft p-3"><strong class="break-words">{{ blocker.problem }}</strong><p>{{ blocker.state }}</p></li></ul></section>

            <!-- Bloc « Dernière évaluation » du parcours de stage (AC 33, FR166). -->
            <section
                v-if="lastEvaluation"
                class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4"
                aria-labelledby="last-evaluation-title"
                data-testid="last-evaluation-block"
            >
                <h2 id="last-evaluation-title" class="text-section-title">{{ lastEvaluation.title }}</h2>
                <p class="font-semibold">{{ lastEvaluation.status }}</p>
                <p class="text-ink-secondary">{{ lastEvaluation.detail }}</p>
                <Link :href="lastEvaluation.action_url" class="touch-target inline-flex items-center font-semibold text-primary">
                    {{ lastEvaluation.action_label }}
                </Link>
            </section>

            </div>
            <!-- Après le bloc d'approbation, toujours premier (cahier d'interface § 5.4). -->
            <h1 id="home-title" class="pt-2 text-section-title text-ink-secondary">Bienvenue dans PTR Staff</h1>
        </div>
    </AppLayout>
</template>
