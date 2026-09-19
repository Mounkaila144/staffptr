<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    attempts: { type: Object, required: true },
    sessions: { type: Array, required: true },
    people: { type: Array, required: true },
    filters: { type: Object, required: true },
    filtersActive: { type: Boolean, required: true },
    hasFailedAttemptsLast30Days: { type: Boolean, required: true },
});
</script>

<template>
    <Head title="Connexions et sessions" />
    <AppLayout title="Connexions et sessions">
        <div class="grid gap-8">
            <header class="grid gap-2">
                <h1 class="text-screen-title">Historique de connexion</h1>
                <p class="text-ink-secondary">Tentatives des 30 derniers jours et sessions actuellement ouvertes.</p>
            </header>

            <form method="get" action="/connexions" class="grid gap-4 rounded-xl border border-separator bg-surface p-4" aria-label="Filtrer l'historique">
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="grid gap-1 text-sm font-semibold">Personne
                        <select name="person_id" :value="filters.person_id ?? ''" class="touch-target rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="">Toutes les personnes</option>
                            <option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Du
                        <input name="from" type="date" :value="filters.from ?? ''" class="touch-target rounded-lg border border-separator bg-surface px-3 font-normal">
                    </label>
                    <label class="grid gap-1 text-sm font-semibold">Au
                        <input name="to" type="date" :value="filters.to ?? ''" class="touch-target rounded-lg border border-separator bg-surface px-3 font-normal">
                    </label>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="touch-target rounded-lg bg-primary px-4 font-semibold text-white">Filtrer</button>
                    <Link v-if="filtersActive" href="/connexions" class="touch-target inline-flex items-center rounded-lg px-4 font-semibold text-primary">Réinitialiser les filtres</Link>
                </div>
            </form>

            <section class="grid gap-4" aria-labelledby="attempts-title">
                <h2 id="attempts-title" class="text-section-title">Tentatives</h2>
                <div v-if="attempts.data.length" class="grid gap-3">
                    <article v-for="attempt in attempts.data" :key="attempt.id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-2">
                        <div><p class="font-semibold">{{ attempt.person }}</p><p class="break-words text-sm text-ink-secondary">{{ attempt.device }}</p></div>
                        <dl class="grid gap-1 text-sm sm:text-right">
                            <div><dt class="inline font-semibold">Résultat : </dt><dd class="inline" :class="attempt.successful ? 'text-success' : 'text-danger'">{{ attempt.result }}</dd></div>
                            <div><dt class="inline font-semibold">Adresse : </dt><dd class="inline">{{ attempt.address }}</dd></div>
                            <div><dt class="inline font-semibold">Date : </dt><dd class="inline">{{ attempt.occurred_at }}</dd></div>
                        </dl>
                    </article>
                </div>
                <div v-else-if="filtersActive" class="grid gap-3 rounded-xl border border-separator bg-surface p-5">
                    <p class="font-semibold">Aucune connexion ne correspond à ces filtres.</p>
                    <Link href="/connexions" class="touch-target inline-flex w-fit items-center font-semibold text-primary">Réinitialiser les filtres</Link>
                </div>
                <p v-else-if="!hasFailedAttemptsLast30Days" class="rounded-xl border border-separator bg-surface p-5 font-semibold">Aucune tentative échouée sur les 30 derniers jours.</p>
                <p v-else class="rounded-xl border border-separator bg-surface p-5 text-ink-secondary">Aucune tentative sur cette page.</p>

                <nav v-if="attempts.prev_page_url || attempts.next_page_url" class="flex items-center justify-between gap-3" aria-label="Pagination des tentatives">
                    <Link v-if="attempts.prev_page_url" :href="attempts.prev_page_url" class="touch-target inline-flex items-center rounded-lg px-3 font-semibold text-primary">Précédent</Link><span v-else />
                    <p class="text-sm text-ink-secondary">Page {{ attempts.current_page }} sur {{ attempts.last_page }}</p>
                    <Link v-if="attempts.next_page_url" :href="attempts.next_page_url" class="touch-target inline-flex items-center rounded-lg px-3 font-semibold text-primary">Suivant</Link><span v-else />
                </nav>
            </section>

            <section class="grid gap-4" aria-labelledby="sessions-title">
                <h2 id="sessions-title" class="text-section-title">Sessions ouvertes</h2>
                <div v-if="sessions.length" class="grid gap-3">
                    <article v-for="session in sessions" :key="session.id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-2">
                        <div><p class="font-semibold">{{ session.person }}</p><p class="break-words text-sm text-ink-secondary">{{ session.device }}</p></div>
                        <dl class="grid gap-1 text-sm sm:text-right">
                            <div><dt class="inline font-semibold">Adresse : </dt><dd class="inline">{{ session.address }}</dd></div>
                            <div><dt class="inline font-semibold">Dernière activité : </dt><dd class="inline">{{ session.last_activity }}</dd></div>
                        </dl>
                    </article>
                </div>
                <p v-else class="rounded-xl border border-separator bg-surface p-5 text-ink-secondary">Aucune session ouverte.</p>
            </section>
        </div>
    </AppLayout>
</template>
