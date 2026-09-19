<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../../../Layouts/AppLayout.vue';

defineProps({ reports: { type: Array, required: true }, focusedReportId: { type: Number, default: null }, success: { type: String, default: null } });
const returnId = ref(null);
const commentId = ref(null);
const decisionForm = useForm({ reason: '' });
const commentForm = useForm({ body: '' });
function validateReport(report) { decisionForm.patch(`/rapports/${report.id}/valider`, { preserveScroll: true }); }
function returnReport(report) { decisionForm.patch(`/rapports/${report.id}/retourner`, { preserveScroll: true, onSuccess: () => { returnId.value = null; decisionForm.reset(); } }); }
function comment(report) { commentForm.post(`/rapports/${report.id}/commentaires`, { preserveScroll: true, onSuccess: () => { commentId.value = null; commentForm.reset(); } }); }
</script>

<template>
    <Head title="Rapports à valider" />
    <AppLayout title="Rapports à valider" active-navigation="daily-report-reviews">
        <div class="grid min-w-0 gap-5">
            <header class="grid gap-2"><h1 class="text-screen-title">Rapports à valider</h1><p class="text-ink-secondary">Les rapports les plus anciens sont présentés en premier.</p></header>
            <p v-if="success" class="rounded-xl border-2 border-success p-4" role="status">{{ success }}</p>
            <section v-if="reports.length === 0" class="rounded-xl border border-separator bg-neutral-soft p-5"><h2 class="text-card-title">Vous êtes à jour.</h2><p>Aucun rapport n’attend votre décision.</p></section>
            <ul v-else class="grid min-w-0 gap-4" aria-label="File des rapports à valider">
                <li v-for="report in reports" :key="report.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap justify-between gap-2"><div><h2 class="text-card-title">{{ report.author }}</h2><p>{{ report.date }} · envoyé {{ report.submitted_at }}</p></div><span class="rounded-full border border-primary px-3 py-1">{{ report.state_label }}</span></div>
                    <dl v-if="report.current" class="grid min-w-0 gap-3 sm:grid-cols-2">
                        <div><dt class="font-semibold">Tâche prévue</dt><dd class="whitespace-pre-wrap break-words">{{ report.current.planned_task }}</dd></div>
                        <div><dt class="font-semibold">Résultat obtenu</dt><dd class="whitespace-pre-wrap break-words">{{ report.current.achieved_result }}</dd></div>
                        <div><dt class="font-semibold">Blocage</dt><dd>{{ report.current.blocker_present ? report.current.blocker_details : 'Non' }}</dd></div>
                        <div><dt class="font-semibold">Prochaine action</dt><dd>{{ report.current.next_action }}</dd></div>
                        <div><dt class="font-semibold">Aide demandée</dt><dd>{{ report.current.help_requested ? report.current.help_details : 'Non' }}</dd></div>
                        <div><dt class="font-semibold">Preuve</dt><dd><a v-if="report.current.evidence_link" :href="report.current.evidence_link" class="break-all text-primary underline" target="_blank" rel="noopener">Ouvrir le lien</a><a v-else-if="report.current.attachment_url" :href="report.current.attachment_url" class="grid w-fit gap-2 font-semibold text-primary underline"><img v-if="report.current.thumbnail_url" :src="report.current.thumbnail_url" alt="" width="96" height="96" class="size-24 rounded-lg object-cover" />{{ report.current.attachment_name }}</a><span v-else>Aucune preuve jointe</span></dd></div>
                    </dl>
                    <details v-if="report.versions.length > 1" class="rounded-lg border border-separator p-3"><summary class="touch-target cursor-pointer font-semibold">Comparer les {{ report.versions.length }} versions</summary><ol class="grid gap-3 pt-3"><li v-for="version in report.versions" :key="version.number" class="rounded-lg bg-neutral-soft p-3"><strong>Version {{ version.number }} · {{ version.created_at }}</strong><p v-if="version.correction_reason">Motif : {{ version.correction_reason }}</p><p>{{ version.achieved_result }}</p></li></ol></details>
                    <ul v-if="report.comments.length" class="grid gap-2"><li v-for="item in report.comments" :key="item.id" class="rounded-lg bg-neutral-soft p-3"><strong>{{ item.author }}</strong> · {{ item.created_at }}<p>{{ item.body }}</p></li></ul>
                    <div class="grid gap-3 sm:flex sm:flex-wrap"><button class="touch-target rounded-lg bg-primary px-4 font-bold text-white" :disabled="decisionForm.processing" @click="validateReport(report)">Valider</button><button class="touch-target rounded-lg border border-danger px-4 font-semibold text-danger" @click="returnId = report.id">Retourner avec un motif</button><button class="touch-target rounded-lg border border-primary px-4 font-semibold text-primary" @click="commentId = report.id">Commenter</button></div>
                    <form v-if="returnId === report.id" class="grid gap-2 rounded-lg border border-danger p-3" @submit.prevent="returnReport(report)"><label class="font-semibold">Motif du retour<textarea v-model="decisionForm.reason" rows="3" required class="mt-2 min-w-0 w-full rounded-lg border border-separator p-3" /></label><p v-if="decisionForm.errors.reason" class="text-danger" role="alert">{{ decisionForm.errors.reason }}</p><button class="touch-target rounded-lg bg-danger px-4 font-bold text-white">Confirmer le retour</button></form>
                    <form v-if="commentId === report.id" class="grid gap-2 rounded-lg border border-separator p-3" @submit.prevent="comment(report)"><label class="font-semibold">Commentaire<textarea v-model="commentForm.body" rows="3" required class="mt-2 min-w-0 w-full rounded-lg border border-separator p-3" /></label><button class="touch-target rounded-lg bg-primary px-4 font-bold text-white">Ajouter le commentaire</button></form>
                </li>
            </ul>
            <Link href="/" class="touch-target inline-flex w-fit items-center font-semibold text-primary">Retour à l’accueil</Link>
        </div>
    </AppLayout>
</template>
