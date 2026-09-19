<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    dailyReport: { type: Object, default: null },
    blockerBlock: { type: Object, default: null },
    approvalQueue: { type: Object, default: null },
    workBlocks: { type: Object, required: true },
    lastEvaluation: { type: Object, default: null },
});
</script>

<template>
    <Head title="Accueil" />
    <AppLayout title="Accueil" active-navigation="home">
        <div class="grid min-w-0 gap-6">
            <section v-if="dailyReport && !dailyReport.after_approval" class="grid min-w-0 gap-3 rounded-xl border-2 border-primary bg-surface p-4" aria-labelledby="daily-report-home-title" data-testid="daily-report-block">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <h1 id="daily-report-home-title" class="break-words text-page-title">{{ dailyReport.title }}</h1>
                    <span class="rounded-full border border-primary px-3 py-1 font-semibold">{{ dailyReport.status }}</span>
                </div>
                <Link :href="dailyReport.action_url" class="touch-target inline-flex w-fit items-center font-semibold text-primary">{{ dailyReport.action_label }}</Link>
            </section>

            <section
                v-if="approvalQueue"
                class="grid min-w-0 gap-4 rounded-xl border-2 border-primary bg-surface p-4 sm:p-6"
                aria-labelledby="approval-queue-title"
                data-testid="approval-queue-block"
            >
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <div class="grid min-w-0 gap-1">
                        <h1 id="approval-queue-title" class="break-words text-page-title">En attente de mon approbation</h1>
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

            <section v-if="dailyReport && dailyReport.after_approval" class="grid min-w-0 gap-3 rounded-xl border-2 border-primary bg-surface p-4" aria-labelledby="daily-report-direction-title" data-testid="daily-report-block">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <h2 id="daily-report-direction-title" class="break-words text-section-title">{{ dailyReport.title }}</h2>
                    <span class="rounded-full border border-primary px-3 py-1 font-semibold">{{ dailyReport.status }}</span>
                </div>
                <Link :href="dailyReport.action_url" class="touch-target inline-flex w-fit items-center font-semibold text-primary">{{ dailyReport.action_label }}</Link>
            </section>

            <section v-if="workBlocks.objectives" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="monthly-objectives-title">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <h2 id="monthly-objectives-title" class="break-words text-section-title">{{ workBlocks.objectives.title }}</h2>
                    <Link :href="workBlocks.objectives.url" class="touch-target inline-flex items-center px-3 font-semibold text-primary">Voir les objectifs</Link>
                </div>
                <ul v-if="workBlocks.objectives.items.length" class="grid min-w-0 gap-3">
                    <li v-for="objective in workBlocks.objectives.items" :key="objective.id" class="grid min-w-0 gap-2 rounded-lg bg-neutral-soft p-3">
                        <div class="flex min-w-0 flex-wrap justify-between gap-2"><span class="break-words font-semibold">{{ objective.title }}</span><span>● {{ objective.state_label }} — {{ objective.tone }}</span></div>
                        <progress :value="objective.progress" max="100" class="h-3 w-full" :aria-label="`Progression ${objective.progress} %`" />
                        <span>{{ objective.progress }} %</span>
                    </li>
                </ul>
                <p v-else>{{ workBlocks.objectives.empty_message }}</p>
            </section>

            <section v-if="workBlocks.todayTasks" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="today-tasks-title">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2"><h2 id="today-tasks-title" class="text-section-title">{{ workBlocks.todayTasks.title }}</h2><Link :href="workBlocks.todayTasks.url" class="touch-target inline-flex items-center px-3 font-semibold text-primary">Ouvrir la liste du jour</Link></div>
                <ul v-if="workBlocks.todayTasks.items.length" class="grid min-w-0 gap-2"><li v-for="task in workBlocks.todayTasks.items" :key="task.id" class="rounded-lg bg-neutral-soft p-3"><strong class="break-words">{{ task.title }}</strong><p>{{ task.status_label }} · {{ task.priority_label }}</p></li></ul>
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

            <section v-if="workBlocks.notifications" class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="notifications-title"><div><h2 id="notifications-title" class="text-section-title">Notifications</h2><p>{{ workBlocks.notifications.count }} non lue{{ workBlocks.notifications.count > 1 ? 's' : '' }}</p></div><Link :href="workBlocks.notifications.url" class="touch-target inline-flex items-center px-3 font-semibold text-primary">Consulter</Link></section>

            <section class="grid gap-4" aria-labelledby="home-title">
            <h1 id="home-title" class="text-screen-title">Bienvenue dans PTR Staff</h1>
            <p class="text-ink-secondary">Votre espace est prêt. Les modules métier apparaîtront ici selon vos droits.</p>
            <Link href="/deconnexion" method="post" as="button" class="touch-target justify-self-start rounded-lg px-3 font-semibold text-primary underline-offset-4 hover:underline">Se déconnecter</Link>
            </section>
        </div>
    </AppLayout>
</template>
