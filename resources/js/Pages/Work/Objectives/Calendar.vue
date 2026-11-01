<script setup>import { Head,Link } from '@inertiajs/vue3'; import EmptyState from '../../../Components/EmptyState.vue'; import AppLayout from '../../../Layouts/AppLayout.vue'; defineProps({objectives:Object,month:String});
// Navigation d’un mois à l’autre : sans elle, les objectifs d’un autre mois
// (une copie vers le mois suivant, par exemple) restaient introuvables.
const shiftMonth = (value, delta) => {
    const [y, m] = value.split("-").map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
};
const monthLabel = (value) => {
    const [y, m] = value.split("-").map(Number);
    return new Intl.DateTimeFormat("fr-FR", { month: "long", year: "numeric" }).format(new Date(y, m - 1, 1));
};
</script>
<template><Head title="Calendrier des objectifs"/><AppLayout title="Calendrier des objectifs"><div class="grid min-w-0 gap-5"><header class="grid gap-2"><h1 class="text-page-title">Calendrier des objectifs</h1><p class="text-ink-secondary">Les objectifs du mois, classés par date limite.</p><Link href="/objectifs" class="touch-target w-fit px-3 font-semibold text-primary">Retour à la liste</Link><nav class="flex flex-wrap items-center gap-2" aria-label="Changer de mois"><Link :href="`/objectifs/calendrier?month=${shiftMonth(month, -1)}`" class="touch-target inline-flex items-center rounded-lg border border-separator px-3 font-semibold text-primary">← Mois précédent</Link><span class="font-semibold">{{ monthLabel(month) }}</span><Link :href="`/objectifs/calendrier?month=${shiftMonth(month, 1)}`" class="touch-target inline-flex items-center rounded-lg border border-separator px-3 font-semibold text-primary">Mois suivant →</Link></nav></header><ol v-if="objectives.data.length" class="grid min-w-0 gap-3" aria-label="Échéances du mois, en liste chronologique"><li v-for="objective in objectives.data" :key="objective.id" class="grid min-w-0 gap-1 rounded-xl border border-separator bg-surface p-4"><time class="font-semibold">{{ objective.due_date_label }}</time><Link :href="`/objectifs/${objective.id}`" class="break-words text-primary">{{ objective.title }}</Link><span>{{ objective.state_label }} — {{ objective.progress }} %</span></li></ol><EmptyState v-else title="Aucune échéance pour ce mois." reason="Les objectifs apparaîtront ici après leur proposition."/></div></AppLayout></template>
