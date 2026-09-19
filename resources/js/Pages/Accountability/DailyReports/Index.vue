<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
defineProps({ analytics: { type: Object, required: true } });
</script>

<template>
    <Head title="Rapports quotidiens" />
    <AppLayout title="Rapports quotidiens" active-navigation="daily-reports-index">
        <div class="grid min-w-0 gap-5">
            <header class="grid gap-2"><h1 class="text-screen-title">Rapports quotidiens</h1><p>{{ analytics.from }} au {{ analytics.to }}</p></header>
            <nav class="flex flex-wrap gap-3" aria-label="Période des rapports"><Link href="/rapports?vue=quotidienne" class="touch-target font-semibold text-primary">Quotidienne</Link><Link href="/rapports?vue=hebdomadaire" class="touch-target font-semibold text-primary">Hebdomadaire</Link><Link href="/rapports?vue=mensuelle" class="touch-target font-semibold text-primary">Mensuelle</Link></nav>
            <section class="grid gap-2 rounded-xl border border-separator bg-surface p-4" aria-labelledby="punctuality-title"><h2 id="punctuality-title" class="text-section-title">Ponctualité globale</h2><p class="text-2xl font-bold">{{ analytics.punctuality.percentage }} %</p><p>{{ analytics.punctuality.on_time }} rapports à l’heure sur {{ analytics.punctuality.expected }} attendus, absences et jours fermés exclus.</p></section>
            <section v-if="analytics.view === 'quotidienne'" class="grid gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="missing-title"><h2 id="missing-title" class="text-section-title">Rapports manquants du jour</h2><p v-if="analytics.missing.length === 0">Tous les rapports du jour ont été envoyés.</p><ul v-else class="grid gap-2"><li v-for="person in analytics.missing" :key="person.user_id" class="rounded-lg bg-neutral-soft p-3">{{ person.name }}</li></ul></section>
            <section class="grid gap-3" aria-labelledby="reports-title"><h2 id="reports-title" class="text-section-title">Rapports reçus</h2><p v-if="analytics.reports.length === 0" class="rounded-xl bg-neutral-soft p-4">Aucun rapport sur cette période. Les rapports apparaîtront après leur envoi.</p><ul v-else class="grid gap-3"><li v-for="report in analytics.reports" :key="report.id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4"><div class="flex flex-wrap justify-between gap-2"><strong>{{ report.author }}</strong><span>{{ report.state_label }}</span></div><p>{{ report.date }} · {{ report.submitted_at }}</p><p class="break-words">{{ report.result }}</p></li></ul><nav v-if="analytics.pagination.last_page > 1" class="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination des rapports"><Link v-if="analytics.pagination.previous_url" :href="analytics.pagination.previous_url" :only="['analytics']" preserve-scroll class="touch-target font-semibold text-primary">Page précédente</Link><span>Page {{ analytics.pagination.current_page }} sur {{ analytics.pagination.last_page }}</span><Link v-if="analytics.pagination.next_url" :href="analytics.pagination.next_url" :only="['analytics']" preserve-scroll class="touch-target font-semibold text-primary">Page suivante</Link></nav></section>
        </div>
    </AppLayout>
</template>
