<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({ reports: { type: Array, default: () => [] }, abilities: { type: Object, required: true } });
const form = useForm({ month: '' }); const reopenId = ref(null); const reason = ref('');
function prepare() { form.post('/finances/rapports-mensuels', { preserveScroll: true }); }
function act(id, action) { useForm(action === 'reouvrir' ? { reason: reason.value } : {}).patch(`/finances/rapports-mensuels/${id}/${action}`, { preserveScroll: true, onSuccess: () => { reopenId.value = null; reason.value = ''; } }); }
</script>

<template>
    <AppLayout title="Rapports financiers" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2"><h1 class="text-page-title">Rapport financier mensuel</h1><p class="text-ink-secondary">Douze lignes ordonnées, contrôlées puis validées par la direction.</p></header>
            <form v-if="abilities.prepare" class="flex min-w-0 flex-wrap items-end gap-3 rounded-xl border border-separator bg-surface p-4" @submit.prevent="prepare"><FormField id="report-month" v-model="form.month" type="month" label="Mois à préparer" required /><AppButton type="submit" variant="principal" :busy="form.processing">Générer</AppButton></form>
            <EmptyState v-if="reports.length === 0" title="Aucun rapport financier mensuel." reason="Générez le rapport du mois à contrôler." />
            <article v-for="report in reports" v-else :key="report.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                <div class="flex flex-wrap justify-between gap-2"><h2 class="text-section-title">{{ report.month_label }} · version {{ report.version }}</h2><strong>{{ report.state }}</strong></div>
                <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"><li v-for="(line, index) in report.lines" :key="line.key" class="grid gap-1 rounded-lg border border-separator p-3"><strong>{{ index + 1 }}. {{ line.label }}</strong><span class="text-card-title">{{ line.value_label }}</span><span v-if="line.not_applicable" class="font-semibold">poste non applicable à ce jour</span><small>Période : {{ line.period }}</small><small>Méthode : {{ line.method }}</small></li></ul>
                <p v-if="report.alert_level" class="font-bold">Niveau d'alerte figé : {{ report.alert_level }}</p>
                <div class="flex flex-wrap gap-2"><AppButton v-if="abilities.control && report.state === 'draft'" variant="secondaire" @click="act(report.id, 'controler')">Contrôler</AppButton><AppButton v-if="abilities.validate && report.state === 'controlled'" variant="principal" @click="act(report.id, 'valider')">Valider et clôturer</AppButton><AppButton v-if="abilities.validate && report.is_closed" variant="secondaire" @click="reopenId = report.id">Rouvrir le mois</AppButton></div>
                <form v-if="reopenId === report.id" class="grid gap-3 rounded-lg border border-warning p-3" @submit.prevent="act(report.id, 'reouvrir')"><FormField :id="`reopen-${report.id}`" v-model="reason" label="Motif obligatoire de réouverture" required /><AppButton type="submit" variant="principal">Confirmer la réouverture</AppButton></form>
            </article>
        </div>
    </AppLayout>
</template>
